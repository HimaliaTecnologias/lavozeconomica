<?php

namespace App\Core\Site;

use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;

/**
 * Relacionadas para portada: prioriza las manuales y completa con automáticas.
 */
final class HomeRelatedArticlesProvider
{
    public const DEFAULT_LIMIT = 3;

    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {}

    /**
     * @param list<Article> $articles Orden de prioridad en portada (hero → banda → secciones…)
     *
     * @return array<int, list<Article>>
     */
    public function mapForArticles(SiteContext $site, array $articles, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min(5, $limit));
        $unique = [];
        $orderedIds = [];
        foreach ($articles as $article) {
            $id = $article->getId();
            if ($id === null || isset($unique[$id])) {
                continue;
            }
            $unique[$id] = $article;
            $orderedIds[] = $id;
        }

        if ($orderedIds === []) {
            return [];
        }

        return $this->buildMap(
            $this->articleRepository($this->siteEmFactory->forContext($site)),
            $unique,
            $orderedIds,
            $limit,
        );
    }

    /**
     * @param array<int, Article> $unique
     * @param list<int>           $orderedIds
     *
     * @return array<int, list<Article>>
     */
    private function buildMap(ArticleRepository $repo, array $unique, array $orderedIds, int $limit): array
    {
        $manualGrouped = $repo->findManualRelatedPublishedGrouped($orderedIds, $limit);
        $usedIds = $orderedIds;
        $map = [];
        $needsFill = [];

        foreach ($orderedIds as $articleId) {
            $related = [];
            foreach ($manualGrouped[$articleId] ?? [] as $candidate) {
                $candidateId = $candidate->getId();
                if ($candidateId === null || \in_array($candidateId, $usedIds, true)) {
                    continue;
                }
                $related[] = $candidate;
                $usedIds[] = $candidateId;
                if (count($related) >= $limit) {
                    break;
                }
            }

            $map[$articleId] = $related;
            if (count($related) < $limit) {
                $needsFill[] = $articleId;
            }
        }

        if ($needsFill === []) {
            return $map;
        }

        $needed = 0;
        foreach ($needsFill as $articleId) {
            $needed += $limit - count($map[$articleId]);
        }

        $pool = $repo->findPublishedRelatedExcluding($needed + 8, $usedIds, null);
        foreach ($needsFill as $articleId) {
            $preferCategoryId = $unique[$articleId]->getCategory()?->getId();
            $map[$articleId] = $this->takeFromPool(
                $map[$articleId],
                $pool,
                $usedIds,
                $limit,
                $preferCategoryId,
            );
        }

        return $map;
    }

    /**
     * @param list<Article>      $current
     * @param list<Article>      $pool
     * @param list<int>          $usedIds
     *
     * @return list<Article>
     */
    private function takeFromPool(
        array $current,
        array &$pool,
        array &$usedIds,
        int $limit,
        ?int $preferCategoryId,
    ): array {
        if (count($current) >= $limit || $pool === []) {
            return $current;
        }

        $remaining = [];
        foreach ($pool as $candidate) {
            if (count($current) >= $limit) {
                $remaining[] = $candidate;
                continue;
            }

            $candidateId = $candidate->getId();
            if ($candidateId === null || \in_array($candidateId, $usedIds, true)) {
                continue;
            }

            $categoryId = $candidate->getCategory()?->getId();
            $isPreferred = $preferCategoryId === null || $categoryId === $preferCategoryId;
            if (!$isPreferred) {
                $remaining[] = $candidate;
                continue;
            }

            $current[] = $candidate;
            $usedIds[] = $candidateId;
        }

        $pool = $remaining;
        if (count($current) >= $limit || $pool === []) {
            return $current;
        }

        $stillRemaining = [];
        foreach ($pool as $candidate) {
            if (count($current) >= $limit) {
                $stillRemaining[] = $candidate;
                continue;
            }

            $candidateId = $candidate->getId();
            if ($candidateId === null || \in_array($candidateId, $usedIds, true)) {
                continue;
            }

            $current[] = $candidate;
            $usedIds[] = $candidateId;
        }
        $pool = $stillRemaining;

        return $current;
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
