<?php

namespace App\Core\Site;

use App\Cache\PublicArticleCache;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;

final class PopularArticlesProvider
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly PublicArticleCache $articleCache,
    ) {}

    /**
     * @param list<int> $categoryIds
     *
     * @return list<Article>
     */
    public function forSite(
        SiteContext $site,
        PopularPeriod $period,
        int $limit,
        ?int $excludeId = null,
        bool $homeOnly = false,
        array $categoryIds = [],
    ): array {
        $repo = $this->articleRepository($this->siteEmFactory->forContext($site));

        return $this->forRepository($repo, $site, $period, $limit, $excludeId, $homeOnly, $categoryIds);
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<Article>
     */
    public function forRepository(
        ArticleRepository $repo,
        ?SiteContext $site,
        PopularPeriod $period,
        int $limit,
        ?int $excludeId = null,
        bool $homeOnly = false,
        array $categoryIds = [],
    ): array {
        $limit = max(1, min(20, $limit));
        $categoryIds = array_values(array_unique(array_filter(
            $categoryIds,
            static fn (mixed $id): bool => is_int($id) ? $id > 0 : (is_numeric($id) && (int) $id > 0),
        )));
        $categoryIds = array_map(static fn (mixed $id): int => (int) $id, $categoryIds);
        sort($categoryIds);

        $since = $period->since();
        $loader = function () use ($repo, $limit, $excludeId, $homeOnly, $categoryIds, $since): array {
            if ($categoryIds !== []) {
                return $repo->findPublishedMostViewedInCategoryIds($categoryIds, $limit, $excludeId, $since);
            }

            return $repo->findPublishedMostViewed($limit, $excludeId, $homeOnly, $since);
        };

        if ($site === null) {
            return $loader();
        }

        $idsKey = $categoryIds === [] ? 'all' : implode('-', $categoryIds);

        return $this->articleCache->rememberList(
            $site,
            $repo,
            sprintf(
                'popular.%s.n%d.h%d.e%s.c%s',
                $period->value,
                $limit,
                $homeOnly ? 1 : 0,
                $excludeId ?? 0,
                $idsKey,
            ),
            $loader,
        );
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
