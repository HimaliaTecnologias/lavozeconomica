<?php

namespace App\Repository\Site;

use App\Editorial\ArticleFeaturedImageGuard;
use App\Entity\Site\Article;
use App\Entity\Site\ArticleRelated;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Entity\Site\Enum\ArticleStatus;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Article>
 */
final class ArticleRepository extends EntityRepository
{
    private ?ArticleFeaturedImageGuard $featuredImageGuard = null;

    public function setFeaturedImageGuard(ArticleFeaturedImageGuard $featuredImageGuard): void
    {
        $this->featuredImageGuard = $featuredImageGuard;
    }

    public function findPublishedBySlug(string $slug): ?Article
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a', 'img', 'cat', 'poll', 'pollOpt', 'video', 'tag')
            ->leftJoin('a.images', 'img')
            ->leftJoin('a.category', 'cat')
            ->leftJoin('a.poll', 'poll')
            ->leftJoin('poll.options', 'pollOpt')
            ->leftJoin('a.video', 'video')
            ->leftJoin('a.tags', 'tag')
            ->where('a.slug = :slug')
            ->andWhere('a.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', ArticleStatus::Published);

        $this->applyPublishedImageVisibilityFilter($qb);

        /** @var Article|null $article */
        $article = $qb->getQuery()->getOneOrNullResult();

        return $this->visibleOrNull($article);
    }

    public function findPublishedById(int $id): ?Article
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a', 'img', 'cat', 'poll', 'pollOpt', 'video', 'tag')
            ->leftJoin('a.images', 'img')
            ->leftJoin('a.category', 'cat')
            ->leftJoin('a.poll', 'poll')
            ->leftJoin('poll.options', 'pollOpt')
            ->leftJoin('a.video', 'video')
            ->leftJoin('a.tags', 'tag')
            ->where('a.id = :id')
            ->andWhere('a.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', ArticleStatus::Published);

        $this->applyPublishedImageVisibilityFilter($qb);

        /** @var Article|null $article */
        $article = $qb->getQuery()->getOneOrNullResult();

        return $this->visibleOrNull($article);
    }

    /**
     * @return list<Article>
     */
    public function findPublishedLatest(int $limit, ?int $excludeId = null, bool $homeOnly = false): array
    {
        $qb = $this->publishedQueryBuilder($homeOnly)
            ->orderBy('a.isPinned', 'DESC')
            ->addOrderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.updatedAt', 'DESC');

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $this->fetchVisibleLimit($qb, $limit);
    }

    /**
     * @param list<int> $excludeIds
     *
     * @return list<Article>
     */
    public function findPublishedRandom(int $limit, array $excludeIds = []): array
    {
        $poolSize = max($limit * 8, 40);
        $qb = $this->publishedQueryBuilder()
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($poolSize);

        if ($excludeIds !== []) {
            $qb->andWhere('a.id NOT IN (:excludeIds)')->setParameter('excludeIds', $excludeIds);
        }

        $pool = $this->onlyVisible($qb->getQuery()->getResult());
        if ($pool === []) {
            return [];
        }

        shuffle($pool);

        return array_slice($pool, 0, $limit);
    }

    /**
     * @return list<Article>
     */
    public function findPublishedMostViewed(
        int $limit,
        ?int $excludeId = null,
        bool $homeOnly = false,
        ?\DateTimeImmutable $publishedSince = null,
    ): array {
        $qb = $this->publishedQueryBuilder($homeOnly)
            ->orderBy('a.viewCount', 'DESC')
            ->addOrderBy('a.publishedAt', 'DESC');

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        if ($publishedSince !== null) {
            $qb->andWhere('a.publishedAt >= :publishedSince')
                ->setParameter('publishedSince', $publishedSince);
        }

        return $this->fetchVisibleLimit($qb, $limit);
    }

    /**
     * @return list<Article>
     */
    public function findPublishedByCategory(int $categoryId, int $limit, ?int $excludeId = null): array
    {
        return $this->findPublishedByCategoryIds([$categoryId], $limit, $excludeId);
    }

    /**
     * @return array{items: list<Article>, total: int}
     */
    public function findPublishedByCategoryPaginated(int $categoryId, int $page, int $perPage, ?int $excludeId = null): array
    {
        return $this->findPublishedByCategoryIdsPaginated([$categoryId], $page, $perPage, $excludeId);
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return array{items: list<Article>, total: int}
     */
    public function findPublishedByCategoryIdsPaginated(array $categoryIds, int $page, int $perPage, ?int $excludeId = null): array
    {
        if ($categoryIds === []) {
            return ['items' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $countQb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->leftJoin('a.category', 'cat')
            ->where('a.status = :status')
            ->andWhere('cat.id IN (:categoryIds)')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('categoryIds', $categoryIds);

        if ($excludeId !== null) {
            $countQb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        $this->applyPublishedImageVisibilityFilter($countQb);

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->publishedQueryBuilder()
            ->andWhere('cat.id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.updatedAt', 'DESC');

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        $items = $this->onlyVisible($qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult());

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * @param list<int> $categoryIds
     */
    public function countPublishedByCategoryIds(array $categoryIds): int
    {
        if ($categoryIds === []) {
            return 0;
        }

        $countQb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->leftJoin('a.category', 'cat')
            ->where('a.status = :status')
            ->andWhere('cat.id IN (:categoryIds)')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('categoryIds', $categoryIds);

        $this->applyPublishedImageVisibilityFilter($countQb);

        return (int) $countQb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<Article>
     */
    public function findPublishedByCategoryIds(array $categoryIds, int $limit, ?int $excludeId = null, bool $homeOnly = false): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $qb = $this->publishedQueryBuilder($homeOnly)
            ->andWhere('cat.id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->orderBy('a.publishedAt', 'DESC');

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return $this->fetchVisibleLimit($qb, $limit);
    }

    /**
     * @return list<Article>
     */
    public function findPublishedMostViewedInCategory(int $categoryId, int $limit, ?int $excludeId = null): array
    {
        return $this->findPublishedMostViewedInCategoryIds([$categoryId], $limit, $excludeId);
    }

    /**
     * @param list<int> $categoryIds
     *
     * @return list<Article>
     */
    public function findPublishedMostViewedInCategoryIds(
        array $categoryIds,
        int $limit,
        ?int $excludeId = null,
        ?\DateTimeImmutable $publishedSince = null,
    ): array {
        if ($categoryIds === []) {
            return [];
        }

        $qb = $this->publishedQueryBuilder()
            ->andWhere('cat.id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->orderBy('a.viewCount', 'DESC')
            ->addOrderBy('a.publishedAt', 'DESC');

        if ($excludeId !== null) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        if ($publishedSince !== null) {
            $qb->andWhere('a.publishedAt >= :publishedSince')
                ->setParameter('publishedSince', $publishedSince);
        }

        return $this->fetchVisibleLimit($qb, $limit);
    }

    /**
     * Otras noticias publicadas, opcionalmente de la misma sección.
     *
     * @param list<int> $excludeIds
     *
     * @return list<Article>
     */
    public function findPublishedRelatedExcluding(
        int $limit,
        array $excludeIds = [],
        ?int $preferCategoryId = null,
    ): array {
        $excludeIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $excludeIds),
            static fn (int $id): bool => $id > 0,
        )));

        $preferred = [];
        if ($preferCategoryId !== null) {
            $preferred = $this->findPublishedRelatedInCategory($preferCategoryId, $limit, $excludeIds);
            if (count($preferred) >= $limit) {
                return $preferred;
            }
        }

        $excludeWithPreferred = $excludeIds;
        foreach ($preferred as $article) {
            $id = $article->getId();
            if ($id !== null) {
                $excludeWithPreferred[] = $id;
            }
        }

        $qb = $this->publishedQueryBuilder()
            ->orderBy('a.publishedAt', 'DESC');

        if ($excludeWithPreferred !== []) {
            $qb->andWhere('a.id NOT IN (:excludeIds)')->setParameter('excludeIds', array_values(array_unique($excludeWithPreferred)));
        }

        $fill = $this->fetchVisibleLimit($qb, $limit - count($preferred));

        return [...$preferred, ...$fill];
    }

    /**
     * Noticias de la misma sección (relacionadas editoriales).
     *
     * @param list<int> $excludeIds
     *
     * @return list<Article>
     */
    public function findPublishedRelatedInCategory(
        int $categoryId,
        int $limit,
        array $excludeIds = [],
    ): array {
        $qb = $this->publishedQueryBuilder()
            ->andWhere('cat.id = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('a.publishedAt', 'DESC');

        $excludeIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $excludeIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($excludeIds !== []) {
            $qb->andWhere('a.id NOT IN (:excludeIds)')->setParameter('excludeIds', $excludeIds);
        }

        return $this->fetchVisibleLimit($qb, $limit);
    }

    /**
     * Noticias de otras secciones (relacionadas por diversidad editorial).
     *
     * @param list<int> $excludeIds
     *
     * @return list<Article>
     */
    public function findPublishedRelated(?int $categoryId, int $currentId, int $limit, array $excludeIds = []): array
    {
        $excludeIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, [...$excludeIds, $currentId]),
            static fn (int $id): bool => $id > 0,
        )));

        $qb = $this->publishedQueryBuilder()
            ->andWhere('a.id NOT IN (:excludeIds)')
            ->setParameter('excludeIds', $excludeIds)
            ->orderBy('a.publishedAt', 'DESC');

        if ($categoryId !== null) {
            $qb->andWhere('cat.id IS NULL OR cat.id != :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        return $this->fetchVisibleLimit($qb, $limit);
    }

    /**
     * Relacionadas: primero las elegidas a mano; completa con automáticas si faltan.
     *
     * @param list<int> $excludeIds
     *
     * @return list<Article>
     */
    public function resolvePublishedRelated(
        int $articleId,
        ?int $categoryId,
        int $limit,
        array $excludeIds = [],
    ): array {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return [];
        }

        $excludeIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, [...$excludeIds, $articleId]),
            static fn (int $id): bool => $id > 0,
        )));

        $manual = $this->findManualRelatedPublished($articleId, $limit, $excludeIds);
        if (count($manual) >= $limit) {
            return array_slice($manual, 0, $limit);
        }

        foreach ($manual as $article) {
            $id = $article->getId();
            if ($id !== null) {
                $excludeIds[] = $id;
            }
        }

        $fill = $this->findPublishedRelatedExcluding(
            $limit - count($manual),
            $excludeIds,
            $categoryId,
        );

        return [...$manual, ...$fill];
    }

    /**
     * Relacionadas vinculadas manualmente (solo publicadas y visibles).
     *
     * @param list<int> $excludeIds
     *
     * @return list<Article>
     */
    public function findManualRelatedPublished(int $articleId, int $limit, array $excludeIds = []): array
    {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return [];
        }

        $excludeIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $excludeIds),
            static fn (int $id): bool => $id > 0,
        )));

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('r', 'a', 'img', 'cat')
            ->from(ArticleRelated::class, 'r')
            ->innerJoin('r.relatedArticle', 'a')
            ->leftJoin('a.images', 'img')
            ->leftJoin('a.category', 'cat')
            ->where('IDENTITY(r.article) = :articleId')
            ->andWhere('a.status = :status')
            ->setParameter('articleId', $articleId)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.id', 'ASC');

        if ($excludeIds !== []) {
            $qb->andWhere('a.id NOT IN (:excludeIds)')->setParameter('excludeIds', $excludeIds);
        }

        /** @var list<ArticleRelated> $links */
        $links = $qb->getQuery()->getResult();
        $out = [];
        foreach ($links as $link) {
            $related = $link->getRelatedArticle();
            if ($this->visibleOrNull($related) === null) {
                continue;
            }
            $out[] = $related;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Relacionadas manuales para varios artículos (una consulta).
     *
     * @param list<int> $articleIds
     *
     * @return array<int, list<Article>>
     */
    public function findManualRelatedPublishedGrouped(array $articleIds, int $limitPerArticle): array
    {
        $articleIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $articleIds),
            static fn (int $id): bool => $id > 0,
        )));
        $limitPerArticle = max(0, $limitPerArticle);
        if ($articleIds === [] || $limitPerArticle === 0) {
            return [];
        }

        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('r', 'a', 'img', 'cat', 'src')
            ->from(ArticleRelated::class, 'r')
            ->innerJoin('r.article', 'src')
            ->innerJoin('r.relatedArticle', 'a')
            ->leftJoin('a.images', 'img')
            ->leftJoin('a.category', 'cat')
            ->where('src.id IN (:articleIds)')
            ->andWhere('a.status = :status')
            ->setParameter('articleIds', $articleIds)
            ->setParameter('status', ArticleStatus::Published)
            ->orderBy('r.sortOrder', 'ASC')
            ->addOrderBy('r.id', 'ASC');

        /** @var list<ArticleRelated> $links */
        $links = $qb->getQuery()->getResult();
        $grouped = [];
        foreach ($links as $link) {
            $sourceId = $link->getArticle()->getId();
            if ($sourceId === null) {
                continue;
            }
            if (!isset($grouped[$sourceId])) {
                $grouped[$sourceId] = [];
            }
            if (count($grouped[$sourceId]) >= $limitPerArticle) {
                continue;
            }
            $related = $link->getRelatedArticle();
            if ($this->visibleOrNull($related) === null) {
                continue;
            }
            $relatedId = $related->getId();
            if ($relatedId === null || $relatedId === $sourceId) {
                continue;
            }
            foreach ($grouped[$sourceId] as $existing) {
                if ($existing->getId() === $relatedId) {
                    continue 2;
                }
            }
            $grouped[$sourceId][] = $related;
        }

        return $grouped;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Article>
     */
    public function findByIdsPreservingOrder(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            return [];
        }

        /** @var list<Article> $articles */
        $articles = $this->createQueryBuilder('a')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($articles as $article) {
            $id = $article->getId();
            if ($id !== null) {
                $byId[$id] = $article;
            }
        }

        $out = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $out[] = $byId[$id];
            }
        }

        return $out;
    }

    /**
     * Búsqueda rápida para el selector de relacionadas en admin.
     *
     * @return list<Article>
     */
    public function searchForRelatedPicker(string $query, int $limit = 12, ?int $excludeId = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'cat')
            ->addSelect('cat')
            ->andWhere('a.status != :archived')
            ->andWhere('a.title LIKE :q OR a.slug LIKE :q OR a.lead LIKE :q')
            ->setParameter('archived', ArticleStatus::Archived)
            ->setParameter('q', '%'.$query.'%')
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults(max(1, min(30, $limit)));

        if ($excludeId !== null && $excludeId > 0) {
            $qb->andWhere('a.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        /** @var list<Article> $items */
        $items = $qb->getQuery()->getResult();

        return $items;
    }

    /**
     * @param list<int> $categoryIds vacío = todas las categorías
     *
     * @return list<Article>
     */
    public function findPublishedSince(?\DateTimeImmutable $since, array $categoryIds, int $limit): array
    {
        $qb = $this->publishedQueryBuilder()
            ->orderBy('a.publishedAt', 'DESC');

        if ($since !== null) {
            $qb->andWhere('a.publishedAt >= :since')
                ->setParameter('since', $since);
        }

        if ($categoryIds !== []) {
            $qb->andWhere('cat.id IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds);
        }

        return $this->fetchVisibleLimit($qb, $limit);
    }

    /**
     * Noticias publicadas en un día calendario (zona Europe/Madrid).
     *
     * @return list<Article>
     */
    public function findPublishedOnDate(\DateTimeImmutable $date): array
    {
        $tz = new \DateTimeZone('Europe/Madrid');
        $local = $date->setTimezone($tz);
        $start = new \DateTimeImmutable($local->format('Y-m-d').' 00:00:00', $tz);
        $end = $start->modify('+1 day');

        return $this->onlyVisible($this->publishedQueryBuilder()
            ->andWhere('a.publishedAt >= :start')
            ->andWhere('a.publishedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.updatedAt', 'DESC')
            ->getQuery()
            ->getResult());
    }

    /**
     * @return array{items: list<Article>, total: int}
     */
    public function findPublishedOnDatePaginated(\DateTimeImmutable $date, int $page, int $perPage): array
    {
        $tz = new \DateTimeZone('Europe/Madrid');
        $local = $date->setTimezone($tz);
        $start = new \DateTimeImmutable($local->format('Y-m-d').' 00:00:00', $tz);
        $end = $start->modify('+1 day');

        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $countQb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :status')
            ->andWhere('a.publishedAt >= :start')
            ->andWhere('a.publishedAt < :end')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        $this->applyPublishedImageVisibilityFilter($countQb);

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $items = $this->onlyVisible($this->publishedQueryBuilder()
            ->andWhere('a.publishedAt >= :start')
            ->andWhere('a.publishedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult());

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Noticias con mejor valoración media (mínimo una valoración).
     *
     * @return list<Article>
     */
    public function findPublishedTopRated(int $limit, int $minRatings = 1): array
    {
        $limit = max(1, $limit);
        $minRatings = max(1, $minRatings);
        $fetch = min(100, max($limit * 3, $limit + 10));

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('a.id AS id', 'AVG(r.score) AS HIDDEN avgScore', 'COUNT(r.id) AS HIDDEN ratingCount')
            ->from(Article::class, 'a')
            ->innerJoin(\App\Entity\Site\ArticleRating::class, 'r', 'WITH', 'r.article = a')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::Published)
            ->groupBy('a.id')
            ->having('COUNT(r.id) >= :minRatings')
            ->setParameter('minRatings', $minRatings)
            ->orderBy('avgScore', 'DESC')
            ->addOrderBy('ratingCount', 'DESC')
            ->addOrderBy('a.publishedAt', 'DESC')
            ->setMaxResults($fetch)
            ->getQuery()
            ->getArrayResult();

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        if ($ids === []) {
            return [];
        }

        return array_slice($this->findPublishedByIdsPreservingOrder($ids), 0, $limit);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Article>
     */
    public function findPublishedByIdsPreservingOrder(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $articles = $this->publishedQueryBuilder()
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($articles as $article) {
            if ($article->getId() !== null) {
                $indexed[$article->getId()] = $article;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $ordered[] = $indexed[$id];
            }
        }

        return $this->onlyVisible($ordered);
    }

    /**
     * @return list<string> fechas Y-m-d con al menos una noticia publicada
     */
    public function findPublishedDatesBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.publishedAt')
            ->andWhere('a.status = :status')
            ->andWhere('a.publishedAt >= :from')
            ->andWhere('a.publishedAt < :to')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getResult();

        $tz = new \DateTimeZone('Europe/Madrid');
        $dates = [];
        foreach ($rows as $row) {
            $publishedAt = $row['publishedAt'] ?? null;
            if ($publishedAt instanceof \DateTimeImmutable) {
                $dates[$publishedAt->setTimezone($tz)->format('Y-m-d')] = true;
            }
        }

        return array_keys($dates);
    }

    public function findFirstPublishedDate(): ?\DateTimeImmutable
    {
        $result = $this->createQueryBuilder('a')
            ->select('MIN(a.publishedAt) AS firstAt')
            ->andWhere('a.status = :status')
            ->andWhere('a.publishedAt IS NOT NULL')
            ->setParameter('status', ArticleStatus::Published)
            ->getQuery()
            ->getSingleScalarResult();

        if ($result === null) {
            return null;
        }

        if ($result instanceof \DateTimeImmutable) {
            return $result;
        }

        if (!is_string($result) || $result === '') {
            return null;
        }

        return new \DateTimeImmutable($result);
    }

    /**
     * @return array{items: list<Article>, total: int}
     */
    public function findPublishedPaginated(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $countQb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::Published);
        $this->applyPublishedImageVisibilityFilter($countQb);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $items = $this->onlyVisible($this->publishedQueryBuilder()
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult());

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Noticias publicadas con intervalo de agenda que solapa el día (Europe/Madrid).
     *
     * @return array{items: list<Article>, total: int}
     */
    public function findPublishedOnAgendaForDatePaginated(\DateTimeImmutable $date, int $page, int $perPage): array
    {
        $tz = new \DateTimeZone('Europe/Madrid');
        $local = $date->setTimezone($tz);
        $dayStart = new \DateTimeImmutable($local->format('Y-m-d').' 00:00:00', $tz);
        $dayEnd = $dayStart->modify('+1 day');

        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $countQb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :status')
            ->andWhere('a.agendaStartsAt IS NOT NULL')
            ->andWhere('a.agendaEndsAt IS NOT NULL')
            ->andWhere('a.agendaStartsAt < :dayEnd')
            ->andWhere('a.agendaEndsAt >= :dayStart')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd);
        $this->applyPublishedImageVisibilityFilter($countQb);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $items = $this->onlyVisible($this->publishedQueryBuilder()
            ->andWhere('a.agendaStartsAt IS NOT NULL')
            ->andWhere('a.agendaEndsAt IS NOT NULL')
            ->andWhere('a.agendaStartsAt < :dayEnd')
            ->andWhere('a.agendaEndsAt >= :dayStart')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->orderBy('a.agendaStartsAt', 'ASC')
            ->addOrderBy('a.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult());

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: list<Article>, total: int}
     */
    public function searchPublished(string $query, int $page, int $perPage): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['items' => [], 'total' => 0];
        }

        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $needle = '%'.$query.'%';
        // Avoid full-body LIKE scans (expensive / DoS-prone); search title, lead, subtitle and tags.
        $textMatch = 'a.title LIKE :q OR a.lead LIKE :q OR a.subtitle LIKE :q OR searchTag.name LIKE :q';

        $countQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id)')
            ->leftJoin('a.tags', 'searchTag')
            ->where('a.status = :status')
            ->andWhere($textMatch)
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('q', $needle);

        $this->applyPublishedImageVisibilityFilter($countQb);

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $items = $this->onlyVisible($this->publishedQueryBuilder()
            ->leftJoin('a.tags', 'searchTag')
            ->andWhere($textMatch)
            ->setParameter('q', $needle)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult());

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{items: list<Article>, total: int}
     */
    public function findPublishedByTagSlugPaginated(string $tagSlug, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $countQb = $this->createQueryBuilder('a')
            ->select('COUNT(DISTINCT a.id)')
            ->innerJoin('a.tags', 'tag')
            ->where('a.status = :status')
            ->andWhere('tag.slug = :tagSlug')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('tagSlug', $tagSlug);

        $this->applyPublishedImageVisibilityFilter($countQb);

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $items = $this->onlyVisible($this->publishedQueryBuilder()
            ->innerJoin('a.tags', 'tag')
            ->andWhere('tag.slug = :tagSlug')
            ->setParameter('tagSlug', $tagSlug)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.updatedAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult());

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @param list<string> $tagSlugs
     *
     * @return list<Article>
     */
    public function findPublishedByAnyTagSlug(array $tagSlugs, int $limit): array
    {
        $tagSlugs = array_values(array_unique(array_filter($tagSlugs, static fn (string $s): bool => $s !== '')));
        if ($tagSlugs === [] || $limit <= 0) {
            return [];
        }

        $qb = $this->publishedQueryBuilder()
            ->innerJoin('a.tags', 'tag')
            ->andWhere('tag.slug IN (:tagSlugs)')
            ->setParameter('tagSlugs', $tagSlugs)
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('a.updatedAt', 'DESC');

        return $this->fetchVisibleLimit($qb, $limit);
    }

    public function incrementViewCount(int $articleId): void
    {
        $this->getEntityManager()->createQueryBuilder()
            ->update(Article::class, 'a')
            ->set('a.viewCount', 'a.viewCount + 1')
            ->where('a.id = :id')
            ->setParameter('id', $articleId)
            ->getQuery()
            ->execute();
    }

    /**
     * @return array{items: list<Article>, total: int}
     */
    public function findForAdminListPaginated(
        ?string $query,
        ?ArticleStatus $status,
        ?array $categoryIds,
        ?string $source,
        string $sort,
        int $page,
        int $perPage,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->adminListQueryBuilder($query, $status, $categoryIds, $source);
        $this->applyAdminListSort($qb, $sort);

        $total = (int) (clone $qb)
            ->select('COUNT(a.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Totales por estado con los mismos filtros (sin filtro de estado).
     *
     * @return array<string, int>
     */
    public function countAdminListByStatus(?string $query, ?array $categoryIds, ?string $source): array
    {
        $qb = $this->adminListQueryBuilder($query, null, $categoryIds, $source)
            ->select('a.status', 'COUNT(a.id) AS cnt')
            ->groupBy('a.status');

        $rows = $qb->getQuery()->getArrayResult();
        $counts = [];
        foreach (ArticleStatus::cases() as $case) {
            $counts[$case->value] = 0;
        }

        foreach ($rows as $row) {
            $status = $row['status'] ?? null;
            $value = $status instanceof ArticleStatus ? $status->value : (string) $status;
            if ($value !== '' && isset($counts[$value])) {
                $counts[$value] = (int) ($row['cnt'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * @return list<Article>
     */
    public function findForAdminList(?string $query, ?ArticleStatus $status, ?int $categoryId, int $limit = 200): array
    {
        $categoryIds = $categoryId !== null ? [$categoryId] : null;

        return $this->findForAdminListPaginated($query, $status, $categoryIds, null, 'created', 1, $limit)['items'];
    }

    private function adminListQueryBuilder(
        ?string $query,
        ?ArticleStatus $status,
        ?array $categoryIds,
        ?string $source,
    ): \Doctrine\ORM\QueryBuilder {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.category', 'cat')
            ->addSelect('cat');

        if ($query !== null && $query !== '') {
            $qb->andWhere('a.title LIKE :q OR a.slug LIKE :q OR a.lead LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if ($status !== null) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }

        if ($categoryIds !== null && $categoryIds !== []) {
            $qb->andWhere('cat.id IN (:categoryIds)')->setParameter('categoryIds', $categoryIds);
        }

        if ($source === 'ingest') {
            $qb->andWhere('a.ingestedStoryId IS NOT NULL');
        } elseif ($source === 'manual') {
            $qb->andWhere('a.ingestedStoryId IS NULL');
        }

        return $qb;
    }

    private function applyAdminListSort(\Doctrine\ORM\QueryBuilder $qb, string $sort): void
    {
        match ($sort) {
            'updated' => $qb->orderBy('a.updatedAt', 'DESC')->addOrderBy('a.id', 'DESC'),
            'published' => $qb->orderBy('a.publishedAt', 'DESC')->addOrderBy('a.id', 'DESC'),
            'title' => $qb->orderBy('a.title', 'ASC')->addOrderBy('a.id', 'DESC'),
            'views' => $qb->orderBy('a.viewCount', 'DESC')->addOrderBy('a.id', 'DESC'),
            default => $qb->orderBy('a.createdAt', 'DESC')->addOrderBy('a.id', 'DESC'),
        };
    }

    /** @return list<Article> */
    public function findRecentUpdated(int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Article> */
    public function findDrafts(int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::Draft)
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<Article> */
    public function findScheduledDue(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.scheduledAt IS NOT NULL')
            ->andWhere('a.scheduledAt <= :now')
            ->setParameter('status', ArticleStatus::Scheduled)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('a.scheduledAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findLatestIngestScheduledAt(): ?\DateTimeImmutable
    {
        $value = $this->createQueryBuilder('a')
            ->select('MAX(a.scheduledAt)')
            ->andWhere('a.status = :status')
            ->andWhere('a.ingestedStoryId IS NOT NULL')
            ->andWhere('a.scheduledAt IS NOT NULL')
            ->setParameter('status', ArticleStatus::Scheduled)
            ->getQuery()
            ->getSingleScalarResult();

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{day: string, count: int}>
     */
    public function countPublishedPerDay(int $days = 30): array
    {
        $since = new \DateTimeImmutable('-'.$days.' days midnight');
        $rows = $this->createQueryBuilder('a')
            ->select('a.publishedAt')
            ->andWhere('a.status = :status')
            ->andWhere('a.publishedAt >= :since')
            ->setParameter('status', ArticleStatus::Published)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        $counts = [];
        for ($i = $days - 1; $i >= 0; --$i) {
            $day = (new \DateTimeImmutable('-'.$i.' days'))->format('Y-m-d');
            $counts[$day] = 0;
        }

        foreach ($rows as $row) {
            $publishedAt = $row['publishedAt'] ?? null;
            if ($publishedAt instanceof \DateTimeImmutable) {
                $day = $publishedAt->format('Y-m-d');
                if (isset($counts[$day])) {
                    ++$counts[$day];
                }
            }
        }

        $result = [];
        foreach ($counts as $day => $count) {
            $result[] = ['day' => $day, 'count' => $count];
        }

        return $result;
    }

    private function publishedQueryBuilder(bool $homeOnly = false): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a', 'img', 'cat')
            ->leftJoin('a.images', 'img')
            ->leftJoin('a.category', 'cat')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::Published);

        $this->applyPublishedImageVisibilityFilter($qb);

        if ($homeOnly) {
            $qb->andWhere('a.showOnHome = :showOnHome')
                ->setParameter('showOnHome', true);
        }

        return $qb;
    }

    private function applyPublishedImageVisibilityFilter(\Doctrine\ORM\QueryBuilder $qb): void
    {
        // Avoid duplicate joins when the caller already joined video.
        $aliases = $qb->getAllAliases();
        if (!in_array('pubVideo', $aliases, true)) {
            $qb->leftJoin('a.video', 'pubVideo');
        }

        // Filtro SQL grueso: sin imagen/vídeo usable no sale a portada/listados.
        // La comprobación fina (archivo en disco, featured real) la hace ArticleFeaturedImageGuard.
        $qb->andWhere(
            $qb->expr()->orX(
                '(a.videoReplacesFeatured = true AND pubVideo.id IS NOT NULL)',
                $qb->expr()->exists(
                    'SELECT 1 FROM App\Entity\Site\ArticleImage pubImg'
                    .' WHERE pubImg.article = a'
                    .' AND pubImg.enhancementStatus != :pubImgFailed'
                    .' AND pubImg.url IS NOT NULL AND pubImg.url != \'\'',
                ),
            ),
        )->setParameter('pubImgFailed', ArticleImageEnhancementStatus::Failed);
    }

    /**
     * @param list<Article> $articles
     *
     * @return list<Article>
     */
    private function onlyVisible(array $articles, ?int $limit = null): array
    {
        // Fail-closed: sin guard no exponemos noticias (mejor vacío que sin imagen).
        if ($this->featuredImageGuard === null) {
            return [];
        }

        $out = [];
        foreach ($articles as $article) {
            if (!$this->featuredImageGuard->isPubliclyVisible($article)) {
                continue;
            }
            $out[] = $article;
            if ($limit !== null && count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    private function visibleOrNull(?Article $article): ?Article
    {
        if ($article === null || $this->featuredImageGuard === null) {
            return null;
        }

        return $this->featuredImageGuard->isPubliclyVisible($article) ? $article : null;
    }

    /**
     * @return list<Article>
     */
    private function fetchVisibleLimit(\Doctrine\ORM\QueryBuilder $qb, int $limit): array
    {
        $limit = max(1, $limit);
        $fetch = min(120, max($limit * 3, $limit + 12));
        $qb->setMaxResults($fetch);

        return $this->onlyVisible($qb->getQuery()->getResult(), $limit);
    }
}
