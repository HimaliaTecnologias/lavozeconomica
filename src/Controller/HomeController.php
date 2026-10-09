<?php

namespace App\Controller;

use App\Cache\PublicArticleCache;
use App\Core\Site\CategorySectionsProvider;
use App\Core\Site\HomeLatestNewsProvider;
use App\Core\Site\HomeRelatedArticlesProvider;
use App\Core\Site\PopularArticlesProvider;
use App\Core\Site\PopularPeriod;
use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        CategorySectionsProvider $categorySectionsProvider,
        PublicArticleCache $articleCache,
        PopularArticlesProvider $popularArticlesProvider,
        HomeLatestNewsProvider $homeLatestNewsProvider,
        HomeRelatedArticlesProvider $homeRelatedArticlesProvider,
        SiteSettingsReader $settingsReader,
    ): Response {
        $site = $holder->get();
        $articles = [];
        $categorySections = [];
        $popularArticles = [];
        $latestNewsBlocks = [];
        $homeRelatedById = [];
        $popularPeriod = PopularPeriod::Week;
        $pageContext = ['theme' => null, 'appearance' => [], 'navCategories' => [], 'layout' => ['homeTemplate' => 'classic', 'homeCategoryArticlesCount' => 4]];
        $seoConfig = null;

        if ($site !== null) {
            try {
                $pageContext = $pageContextFactory->build($site);
                $seoConfig = $settingsReader->getSeoConfig($site);
                $em = $siteEmFactory->forContext($site);
                $repo = $this->articleRepository($em);
                $sectionCount = max(2, min(4, $pageContext['layout']['homeCategoryArticlesCount'] ?? 4));

                $articles = $articleCache->rememberList(
                    $site,
                    $repo,
                    'home.latest.30',
                    static fn (): array => $repo->findPublishedLatest(30, null, true),
                );
                $popularArticles = $popularArticlesProvider->forRepository(
                    $repo,
                    $site,
                    $popularPeriod,
                    max(3, min(12, (int) ($pageContext['layout']['sidebarPopularCount'] ?? 5))),
                    null,
                    true,
                );

                $categorySections = $categorySectionsProvider->forNavCategories(
                    $site,
                    $pageContext['navCategories'],
                    $sectionCount,
                );

                $relatedSources = $this->collectHomeArticlesForRelated($articles, $categorySections);
                $homeRelatedById = $homeRelatedArticlesProvider->mapForArticles(
                    $site,
                    $relatedSources,
                    HomeRelatedArticlesProvider::DEFAULT_LIMIT,
                );
                $latestNewsBlocks = $homeLatestNewsProvider->build($articles, $homeRelatedById);
            } catch (\Throwable) {
                $articles = [];
                $categorySections = [];
                $popularArticles = [];
                $latestNewsBlocks = [];
                $homeRelatedById = [];
            }
        }

        $siteName = $site?->name ?? 'La Voz Económica';
        $homeTitle = $seoConfig?->resolvedHomeTitle($siteName) ?? $siteName;
        $homeDescription = $seoConfig?->metaDescription
            ?: ($pageContext['appearance']['metaDescription'] ?? ($pageContext['appearance']['tagline'] ?? ''));

        return $this->render('public/home.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'articles' => $articles,
            'popularArticles' => $popularArticles,
            'popularPeriod' => $popularPeriod->value,
            'categorySections' => $categorySections,
            'latestNewsBlocks' => $latestNewsBlocks,
            'homeRelatedById' => $homeRelatedById,
            'seoCanonical' => $this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'seoType' => 'website',
            'seoTitle' => $homeTitle,
            'seoDescription' => $homeDescription !== '' ? $homeDescription : null,
            'seoImage' => $seoConfig?->defaultOgImage ?: null,
        ]);
    }

    /**
     * @param list<Article> $homeArticles
     * @param list<array{category: mixed, articles: list<Article>}> $categorySections
     *
     * @return list<Article>
     */
    private function collectHomeArticlesForRelated(array $homeArticles, array $categorySections): array
    {
        $out = [];
        foreach ($homeArticles as $article) {
            $out[] = $article;
        }
        foreach ($categorySections as $section) {
            foreach ($section['articles'] as $article) {
                $out[] = $article;
            }
        }

        return $out;
    }

    private function articleRepository(\Doctrine\ORM\EntityManagerInterface $em): ArticleRepository
    {
        $repo = $em->getRepository(Article::class);
        if (!$repo instanceof ArticleRepository) {
            throw new \RuntimeException('ArticleRepository no disponible.');
        }

        return $repo;
    }
}
