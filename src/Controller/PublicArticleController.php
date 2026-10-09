<?php

namespace App\Controller;

use App\Cache\PublicArticleCache;
use App\Core\Site\CategorySectionsProvider;
use App\Core\Site\PopularArticlesProvider;
use App\Core\Site\PopularPeriod;
use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Editorial\PollVoteService;
use App\Repository\Site\ArticleCommentRepository;
use App\Repository\Site\ArticleRatingRepository;
use App\Repository\Site\ArticleRepository;
use App\Entity\Platform\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicArticleController extends AbstractController
{
    private const CATEGORY_FEED_PER_PAGE = 3;
    private const INTEREST_COUNT = 8;
    private const MANUAL_RELATED_COUNT = 8;
    #[Route(
        '/noticias/{slug}',
        name: 'public_article_show',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET'],
    )]
    public function show(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        PollVoteService $pollVoteService,
        PublicArticleCache $articleCache,
        PopularArticlesProvider $popularArticlesProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $contentModules = $pageContext['contentModulesConfig'];
        $relatedModuleCount = self::INTEREST_COUNT;
        $mainPopularCount = max(3, min(12, (int) ($pageContext['layout']['sidebarPopularCount'] ?? 5)));
        $mainLatestCount = max(3, min(12, (int) ($pageContext['layout']['sidebarLatestCount'] ?? 5)));
        $loadCategorySections = false;
        foreach ($contentModules->enabledFor('article') as $module) {
            if ($module->type->value === 'related' && $module->count > 0) {
                $relatedModuleCount = $module->count;
            }
            if ($module->type->value === 'popular' && $module->count > $mainPopularCount) {
                $mainPopularCount = $module->count;
            }
            if ($module->type->value === 'latest' && $module->count > $mainLatestCount) {
                $mainLatestCount = $module->count;
            }
            if ($module->type->value === 'category_sections') {
                $loadCategorySections = true;
            }
        }
        $em = $siteEmFactory->forContext($site);
        $repo = $this->articleRepository($em);

        $normalizedSlug = strtolower($slug);
        if ($slug !== $normalizedSlug) {
            return $this->redirectToRoute('public_article_show', ['slug' => $normalizedSlug], Response::HTTP_MOVED_PERMANENTLY);
        }

        $article = $articleCache->rememberPublishedArticle($site, $repo, $normalizedSlug);
        if ($article === null) {
            throw $this->createNotFoundException('Noticia no encontrada.');
        }

        if ($article->getSlug() !== $normalizedSlug) {
            return $this->redirectToRoute('public_article_show', ['slug' => $article->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $articleId = $article->getId();
        if ($articleId !== null) {
            $repo->incrementViewCount($articleId);
            $article->incrementViewCount();
        }

        $category = $article->getCategory();
        $categoryId = $category?->getId();

        $categoryFeedItems = [];
        $categoryFeedHasMore = false;
        $categoryFeedTotal = 0;
        $categoryFeedPage = 1;
        $categoryFeedTotalPages = 1;
        if ($categoryId !== null) {
            $feedResult = $articleCache->rememberPage(
                $site,
                $repo,
                sprintf('article.%d.catfeed.v3.%d.n%d.p1', $articleId ?? 0, $categoryId, self::CATEGORY_FEED_PER_PAGE),
                static fn (): array => $repo->findPublishedByCategoryPaginated(
                    $categoryId,
                    1,
                    self::CATEGORY_FEED_PER_PAGE,
                    $articleId,
                ),
            );
            $categoryFeedItems = $feedResult['items'];
            $categoryFeedTotal = $feedResult['total'];
            $categoryFeedTotalPages = max(1, (int) ceil($categoryFeedTotal / self::CATEGORY_FEED_PER_PAGE));
            $categoryFeedHasMore = $categoryFeedTotalPages > 1;
        }

        $manualRelatedArticles = [];
        $relatedArticles = [];
        if ($articleId !== null) {
            $manualRelatedArticles = $articleCache->rememberList(
                $site,
                $repo,
                sprintf('article.%d.manual-related.v1.%d', $articleId, self::MANUAL_RELATED_COUNT),
                static fn (): array => $repo->findManualRelatedPublished(
                    $articleId,
                    self::MANUAL_RELATED_COUNT,
                ),
            );

            $excludeForInterest = [$articleId];
            foreach ($manualRelatedArticles as $manualArticle) {
                $manualId = $manualArticle->getId();
                if ($manualId !== null) {
                    $excludeForInterest[] = $manualId;
                }
            }
            foreach ($categoryFeedItems as $categoryArticle) {
                $categoryArticleId = $categoryArticle->getId();
                if ($categoryArticleId !== null) {
                    $excludeForInterest[] = $categoryArticleId;
                }
            }

            $relatedArticles = $articleCache->rememberList(
                $site,
                $repo,
                sprintf('article.%d.interest.v2.%d', $articleId, $relatedModuleCount),
                static function () use ($repo, $categoryId, $articleId, $excludeForInterest, $relatedModuleCount): array {
                    $interest = $repo->findPublishedRelated(
                        $categoryId,
                        $articleId,
                        $relatedModuleCount,
                        $excludeForInterest,
                    );
                    if (count($interest) >= $relatedModuleCount) {
                        return $interest;
                    }

                    $exclude = $excludeForInterest;
                    foreach ($interest as $item) {
                        $id = $item->getId();
                        if ($id !== null) {
                            $exclude[] = $id;
                        }
                    }

                    $fill = $repo->findPublishedRelatedExcluding(
                        $relatedModuleCount - count($interest),
                        $exclude,
                        null,
                    );

                    return [...$interest, ...$fill];
                },
            );
        }
        $latestArticles = $articleCache->rememberList(
            $site,
            $repo,
            sprintf('article.%d.latest.%d', $articleId ?? 0, $mainLatestCount),
            static function () use ($repo, $articleId, $mainLatestCount): array {
                return $repo->findPublishedLatest($mainLatestCount, $articleId);
            },
        );
        $popularPeriod = PopularPeriod::Week;
        $popularArticles = $popularArticlesProvider->forRepository(
            $repo,
            $site,
            $popularPeriod,
            $mainPopularCount,
            $articleId,
        );

        $categorySections = [];
        if ($loadCategorySections) {
            $sectionCount = max(2, min(4, (int) ($pageContext['layout']['homeCategoryArticlesCount'] ?? 4)));
            $categorySections = $categorySectionsProvider->forNavCategories(
                $site,
                $pageContext['navCategories'],
                $sectionCount,
            );
        }

        $poll = $article->getPoll();
        $pollVoted = false;
        if ($poll !== null) {
            $voterKey = $pollVoteService->resolveVoterKey($request);
            $pollVoted = $pollVoteService->hasVoted($poll, $voterKey, $em);
        }

        $comments = [];
        $ratingSummary = ['average' => 0.0, 'count' => 0];
        $userRating = null;
        $commentRepo = $em->getRepository(\App\Entity\Site\ArticleComment::class);
        if ($commentRepo instanceof ArticleCommentRepository) {
            $comments = $commentRepo->findForArticle($article);
        }
        $ratingRepo = $em->getRepository(\App\Entity\Site\ArticleRating::class);
        if ($ratingRepo instanceof ArticleRatingRepository) {
            $ratingSummary = $ratingRepo->summarizeForArticle($article);
            $currentUser = $this->getUser();
            if ($currentUser instanceof User && $currentUser->getId() !== null) {
                $userRating = $ratingRepo->findForUser($article, $currentUser->getId());
            }
        }

        return $this->render('public/article/show.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'contentModulesConfig' => $contentModules,
            'article' => $article,
            'pollVoted' => $pollVoted,
            'comments' => $comments,
            'ratingSummary' => $ratingSummary,
            'userRating' => $userRating,
            'categoryFeedItems' => $categoryFeedItems,
            'categoryFeedHasMore' => $categoryFeedHasMore,
            'categoryFeedTotal' => $categoryFeedTotal,
            'categoryFeedPage' => $categoryFeedPage,
            'categoryFeedTotalPages' => $categoryFeedTotalPages,
            'manualRelatedArticles' => $manualRelatedArticles,
            'relatedArticles' => $relatedArticles,
            'latestArticles' => $latestArticles,
            'popularArticles' => $popularArticles,
            'categorySections' => $categorySections,
            'popularPeriod' => $popularPeriod->value,
            'seoCanonical' => $this->generateUrl('public_article_show', ['slug' => $article->getSlug()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            'seoDescription' => $this->articleDescription($article),
            'seoTitle' => $article->getTitle().' — '.$site->name,
            'seoType' => 'article',
            'seoImage' => $article->getFeaturedImage()?->getUrl(),
            'seoImageAlt' => $article->getFeaturedImage()?->getAlt() ?: $article->getTitle(),
            'seoPublishedTime' => $article->getPublishedAt()?->format(\DateTimeInterface::ATOM),
            'seoModifiedTime' => $article->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'seoAuthor' => $article->getAuthorName() ?: null,
            'seoSection' => $article->getCategory()?->getName(),
            'seoTags' => array_values(array_map(
                static fn (\App\Entity\Site\ArticleTag $tag): string => $tag->getName(),
                $article->getTags()->toArray(),
            )),
            'seoRssUrl' => $article->getCategory() !== null
                ? $this->generateUrl('public_category_rss', ['slug' => $article->getCategory()->getSlug()], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL)
                : null,
            'seoRssTitle' => $article->getCategory()?->getName() ?? $site->name,
        ]);
    }

    private function articleDescription(\App\Entity\Site\Article $article): string
    {
        $text = $article->getLead() ?: $article->getSubtitle() ?: '';
        if ($text === '' && $article->getBodyHtml() !== '') {
            $text = strip_tags($article->getBodyHtml());
        }

        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        return $text !== '' ? $text : $article->getTitle();
    }

    #[Route('/noticias/{slug}/feed-categoria', name: 'public_article_category_feed', methods: ['GET'])]
    public function categoryFeed(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        PublicArticleCache $articleCache,
    ): JsonResponse {
        $site = $holder->require();
        $em = $siteEmFactory->forContext($site);
        $repo = $this->articleRepository($em);

        $article = $repo->findPublishedBySlug($slug);
        $category = $article?->getCategory();
        if ($article === null || $category === null || $category->getId() === null) {
            return new JsonResponse(['error' => 'No disponible.'], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $result = $articleCache->rememberPage(
            $site,
            $repo,
            sprintf(
                'article.%d.catfeed.v3.%d.n%d.p%d',
                $article->getId() ?? 0,
                $category->getId(),
                self::CATEGORY_FEED_PER_PAGE,
                $page,
            ),
            static fn (): array => $repo->findPublishedByCategoryPaginated(
                $category->getId(),
                $page,
                self::CATEGORY_FEED_PER_PAGE,
                $article->getId(),
            ),
        );

        $totalPages = max(1, (int) ceil($result['total'] / self::CATEGORY_FEED_PER_PAGE));
        if ($page > $totalPages) {
            return new JsonResponse([
                'html' => '',
                'hasMore' => false,
                'page' => $page,
                'totalPages' => $totalPages,
                'total' => $result['total'],
            ]);
        }

        $html = $this->renderView('public/article/_category_feed_items.html.twig', [
            'articles' => $result['items'],
        ]);

        return new JsonResponse([
            'html' => $html,
            'hasMore' => $page < $totalPages,
            'page' => $page,
            'nextPage' => $page < $totalPages ? $page + 1 : null,
            'prevPage' => $page > 1 ? $page - 1 : null,
            'totalPages' => $totalPages,
            'total' => $result['total'],
        ]);
    }

    private function articleRepository(\Doctrine\ORM\EntityManagerInterface $em): ArticleRepository
    {
        $repo = $em->getRepository(\App\Entity\Site\Article::class);
        if (!$repo instanceof ArticleRepository) {
            throw new \RuntimeException('ArticleRepository no disponible.');
        }

        return $repo;
    }
}
