<?php

namespace App\Core\Site;

use App\Cache\PublicArticleCache;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;

final class ArchiveSidebarProvider
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly PublicArticleCache $articleCache,
        private readonly PopularArticlesProvider $popularArticlesProvider,
        private readonly SiteSettingsReader $settingsReader,
    ) {}

    /** @return array{latestArticles: list, popularArticles: list, topRatedArticles: list, popularPeriod: string} */
    public function forSite(SiteContext $site): array
    {
        $repo = $this->articleRepository($this->siteEmFactory->forContext($site));

        return $this->forRepository($repo, $site);
    }

    /** @return array{latestArticles: list, popularArticles: list, topRatedArticles: list, popularPeriod: string} */
    public function forRepository(ArticleRepository $repo, ?SiteContext $site = null): array
    {
        $popularPeriod = PopularPeriod::Week;
        $popularLimit = 5;
        $latestLimit = 5;
        if ($site !== null) {
            $layout = $this->settingsReader->getLayoutConfig($site);
            $popularLimit = $layout->sidebarPopularCount;
            $latestLimit = $layout->sidebarLatestCount;
        }

        if ($site === null) {
            return [
                'latestArticles' => $repo->findPublishedLatest($latestLimit),
                'popularArticles' => $this->popularArticlesProvider->forRepository(
                    $repo,
                    null,
                    $popularPeriod,
                    $popularLimit,
                ),
                'topRatedArticles' => $repo->findPublishedTopRated($popularLimit),
                'popularPeriod' => $popularPeriod->value,
            ];
        }

        return [
            'latestArticles' => $this->articleCache->rememberList(
                $site,
                $repo,
                sprintf('sidebar.latest.%d', $latestLimit),
                fn (): array => $repo->findPublishedLatest($latestLimit),
            ),
            'popularArticles' => $this->popularArticlesProvider->forRepository(
                $repo,
                $site,
                $popularPeriod,
                $popularLimit,
            ),
            'topRatedArticles' => $this->articleCache->rememberList(
                $site,
                $repo,
                sprintf('sidebar.toprated.%d', $popularLimit),
                fn (): array => $repo->findPublishedTopRated($popularLimit),
            ),
            'popularPeriod' => $popularPeriod->value,
        ];
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
