<?php

namespace App\Repository\Site;

use App\Entity\Site\AdminActivityLog;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<AdminActivityLog>
 */
final class AdminActivityLogRepository extends EntityRepository
{
    /** @return list<AdminActivityLog> */
    public function findRecent(int $limit = 12): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Última actividad por usuario (user_id => AdminActivityLog).
     *
     * @param list<int> $userIds
     *
     * @return array<int, AdminActivityLog>
     */
    public function findLatestByUserIds(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter($userIds, static fn (int $id): bool => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        /** @var list<AdminActivityLog> $rows */
        $rows = $this->createQueryBuilder('l')
            ->andWhere('l.userId IN (:ids)')
            ->setParameter('ids', $userIds)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($rows as $row) {
            $uid = $row->getUserId();
            if (!isset($latest[$uid])) {
                $latest[$uid] = $row;
            }
        }

        return $latest;
    }
}
