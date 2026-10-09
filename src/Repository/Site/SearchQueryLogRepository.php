<?php

namespace App\Repository\Site;

use App\Entity\Site\SearchQueryLog;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<SearchQueryLog>
 */
final class SearchQueryLogRepository extends EntityRepository
{
    /**
     * @return array{items: list<SearchQueryLog>, total: int}
     */
    public function searchPaginated(string $query, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $qb = $this->createQueryBuilder('l');
        if ($query !== '') {
            $escaped = addcslashes(mb_strtolower($query), '%_\\');
            $qb->andWhere('LOWER(l.query) LIKE :q')
                ->setParameter('q', '%'.$escaped.'%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<SearchQueryLog> $items */
        $items = $qb->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['items' => $items, 'total' => $total];
    }

    public function countSince(\DateTimeImmutable $from): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.createdAt >= :from')
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countDistinctQueriesSince(\DateTimeImmutable $from): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(DISTINCT l.query)')
            ->andWhere('l.createdAt >= :from')
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{query: string, total: int}>
     */
    public function topQueriesSince(\DateTimeImmutable $from, int $limit = 15): array
    {
        /** @var list<array{query: string, total: mixed}> $rows */
        $rows = $this->createQueryBuilder('l')
            ->select('l.query AS query', 'COUNT(l.id) AS total')
            ->andWhere('l.createdAt >= :from')
            ->setParameter('from', $from)
            ->groupBy('l.query')
            ->orderBy('COUNT(l.id)', 'DESC')
            ->addOrderBy('l.query', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'query' => (string) $row['query'],
                'total' => (int) $row['total'],
            ];
        }

        return $out;
    }
}
