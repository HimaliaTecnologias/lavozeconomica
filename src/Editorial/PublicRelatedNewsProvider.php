<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;

final class PublicRelatedNewsProvider
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {}

    /**
     * @return array{latestNews: list<Article>, interestNews: list<Article>}
     */
    public function forCommunityPage(SiteContext $site, int $latestLimit = 6, int $interestLimit = 6): array
    {
        $repo = $this->articleRepository($site);
        if ($repo === null) {
            return ['latestNews' => [], 'interestNews' => []];
        }

        $latestNews = $repo->findPublishedLatest($latestLimit);
        $excludeIds = [];
        foreach ($latestNews as $article) {
            $id = $article->getId();
            if ($id !== null) {
                $excludeIds[] = $id;
            }
        }

        return [
            'latestNews' => $latestNews,
            'interestNews' => $repo->findPublishedRandom($interestLimit, $excludeIds),
        ];
    }

    private function articleRepository(SiteContext $site): ?ArticleRepository
    {
        $repo = $this->siteEmFactory->forContext($site)->getRepository(Article::class);
        if (!$repo instanceof ArticleRepository) {
            return null;
        }

        return $repo;
    }
}
