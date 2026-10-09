<?php

namespace App\Controller;

use App\Cache\PublicArticleCache;
use App\Core\Site\CategorySectionsProvider;
use App\Core\Site\PopularArticlesProvider;
use App\Core\Site\PopularPeriod;
use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Category;
use App\Repository\Site\ArticleRepository;
use App\Repository\Site\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicCategoryController extends AbstractController
{
    private const PER_PAGE = 25;

    #[Route(
        '/categoria/{slug}/pagina/{page}',
        name: 'public_category_show_paged',
        requirements: ['slug' => '[a-z0-9-]+', 'page' => '\d+'],
        methods: ['GET'],
    )]
    public function showPaged(
        string $slug,
        int $page,
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        PublicArticleCache $articleCache,
        PopularArticlesProvider $popularArticlesProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        return $this->renderCategory($slug, $page, $holder, $pageContextFactory, $siteEmFactory, $articleCache, $popularArticlesProvider, $categorySectionsProvider);
    }

    #[Route(
        '/categoria/{slug}',
        name: 'public_category_show',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET'],
    )]
    public function show(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        PublicArticleCache $articleCache,
        PopularArticlesProvider $popularArticlesProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        $page = max(1, (int) $request->query->get('page', 1));

        if ($page > 1) {
            return $this->redirectToRoute('public_category_show_paged', [
                'slug' => strtolower($slug),
                'page' => $page,
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->renderCategory($slug, 1, $holder, $pageContextFactory, $siteEmFactory, $articleCache, $popularArticlesProvider, $categorySectionsProvider);
    }

    private function renderCategory(
        string $slug,
        int $page,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        PublicArticleCache $articleCache,
        PopularArticlesProvider $popularArticlesProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $em = $siteEmFactory->forContext($site);
        $categoryRepo = $this->categoryRepository($em);

        $normalizedSlug = strtolower($slug);
        if ($slug !== $normalizedSlug) {
            return $page > 1
                ? $this->redirectToRoute('public_category_show_paged', ['slug' => $normalizedSlug, 'page' => $page], Response::HTTP_MOVED_PERMANENTLY)
                : $this->redirectToRoute('public_category_show', ['slug' => $normalizedSlug], Response::HTTP_MOVED_PERMANENTLY);
        }

        $category = $categoryRepo->findOneBySlugWithRelations($normalizedSlug);
        if ($category === null) {
            throw $this->createNotFoundException('Sección no encontrada.');
        }

        if ($category->getSlug() !== $normalizedSlug) {
            return $page > 1
                ? $this->redirectToRoute('public_category_show_paged', ['slug' => $category->getSlug(), 'page' => $page], Response::HTTP_MOVED_PERMANENTLY)
                : $this->redirectToRoute('public_category_show', ['slug' => $category->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $repo = $this->articleRepository($em);
        $categoryIds = $categoryRepo->collectSelfAndDescendantIds($category);
        if ($categoryIds === []) {
            throw $this->createNotFoundException('Sección no encontrada.');
        }

        $page = max(1, $page);
        $idsKey = implode('-', $categoryIds);
        $result = $articleCache->rememberPage(
            $site,
            $repo,
            sprintf('cat.%s.p%d.n%d', $idsKey, $page, self::PER_PAGE),
            static fn (): array => $repo->findPublishedByCategoryIdsPaginated($categoryIds, $page, self::PER_PAGE),
        );
        $totalArticles = $result['total'];
        $totalPages = max(1, (int) ceil($totalArticles / self::PER_PAGE));

        if ($page > $totalPages) {
            if ($totalPages > 1) {
                return $this->redirectToRoute('public_category_show_paged', [
                    'slug' => $category->getSlug(),
                    'page' => $totalPages,
                ], Response::HTTP_MOVED_PERMANENTLY);
            }

            return $this->redirectToRoute('public_category_show', ['slug' => $category->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $articles = $result['items'];
        $contentModules = $pageContext['contentModulesConfig'];
        $popularPeriod = PopularPeriod::Week;
        $popularLimit = max(3, min(12, (int) ($pageContext['layout']['sidebarPopularCount'] ?? 5)));
        $latestLimit = max(3, min(12, (int) ($pageContext['layout']['sidebarLatestCount'] ?? 5)));
        $relatedLimit = 4;
        $loadCategorySections = false;
        $loadRelated = false;
        foreach ($contentModules->enabledFor('category') as $module) {
            if ($module->type->value === 'popular' && $module->count > $popularLimit) {
                $popularLimit = $module->count;
            }
            if ($module->type->value === 'latest' && $module->count > $latestLimit) {
                $latestLimit = $module->count;
            }
            if ($module->type->value === 'related') {
                $loadRelated = true;
                if ($module->count > 0) {
                    $relatedLimit = $module->count;
                }
            }
            if ($module->type->value === 'category_sections') {
                $loadCategorySections = true;
            }
        }
        $popularInCategory = $popularArticlesProvider->forRepository(
            $repo,
            $site,
            $popularPeriod,
            $popularLimit,
            null,
            false,
            $categoryIds,
        );
        $latestOverall = $articleCache->rememberList(
            $site,
            $repo,
            sprintf('cat.latest.%d', $latestLimit),
            function () use ($repo, $latestLimit): array {
                return $repo->findPublishedLatest($latestLimit);
            },
        );

        $excludeIds = [];
        foreach ($articles as $listed) {
            $listedId = $listed->getId();
            if ($listedId !== null) {
                $excludeIds[] = $listedId;
            }
        }
        $relatedArticles = [];
        if ($loadRelated) {
            $relatedArticles = $articleCache->rememberList(
                $site,
                $repo,
                sprintf('cat.%s.related.%d', $idsKey, $relatedLimit),
                static fn (): array => $repo->findPublishedRelatedExcluding($relatedLimit, $excludeIds, null),
            );
        }

        $categorySections = [];
        if ($loadCategorySections) {
            $sectionCount = max(2, min(4, (int) ($pageContext['layout']['homeCategoryArticlesCount'] ?? 4)));
            $categorySections = $categorySectionsProvider->forNavCategories(
                $site,
                $pageContext['navCategories'],
                $sectionCount,
            );
        }

        $subcategories = $category->isRoot()
            ? $category->getChildren()->toArray()
            : ($category->getParent()?->getChildren()->toArray() ?? []);

        $navRoots = $categoryRepo->findNavRoots();

        $canonical = $page > 1
            ? $this->generateUrl('public_category_show_paged', ['slug' => $category->getSlug(), 'page' => $page], UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->generateUrl('public_category_show', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        $description = $category->getDescription() !== ''
            ? $category->getDescription()
            : 'Noticias de '.$category->getName().' en '.$site->name;

        return $this->render('public/category/show.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'contentModulesConfig' => $contentModules,
            'category' => $category,
            'subcategories' => $subcategories,
            'navRoots' => $navRoots,
            'articles' => $articles,
            'popularInCategory' => $popularInCategory,
            'popularArticles' => $popularInCategory,
            'popularPeriod' => $popularPeriod->value,
            'categoryIds' => $categoryIds,
            'latestOverall' => $latestOverall,
            'latestArticles' => $latestOverall,
            'relatedArticles' => $relatedArticles,
            'categorySections' => $categorySections,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalArticles' => $totalArticles,
            'perPage' => self::PER_PAGE,
            'seoCanonical' => $canonical,
            'seoDescription' => $description,
            'seoTitle' => $category->getName().' — '.$site->name,
            'seoRobots' => $page > 1 ? 'noindex, follow' : null,
            'seoRssUrl' => $this->generateUrl('public_category_rss', ['slug' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
            'seoRssTitle' => $category->getName(),
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

    private function categoryRepository(\Doctrine\ORM\EntityManagerInterface $em): CategoryRepository
    {
        $repo = $em->getRepository(Category::class);
        if (!$repo instanceof CategoryRepository) {
            throw new \RuntimeException('CategoryRepository no disponible.');
        }

        return $repo;
    }
}
