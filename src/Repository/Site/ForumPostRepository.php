<?php

namespace App\Repository\Site;

use App\Entity\Site\ForumPost;
use App\Entity\Site\ForumTopic;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ForumPost>
 */
final class ForumPostRepository extends EntityRepository
{
    /** @return list<ForumPost> */
    public function findVisibleForTopic(ForumTopic $topic, int $limit = 200): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.topic = :topic')
            ->andWhere('p.isHidden = false')
            ->setParameter('topic', $topic)
            ->orderBy('p.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<ForumPost> */
    public function findRecentForAdmin(int $limit = 80): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.topic', 't')
            ->addSelect('t')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUserId(int $userId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ForumPost> */
    public function findByUserId(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.topic', 't')
            ->addSelect('t')
            ->andWhere('p.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
