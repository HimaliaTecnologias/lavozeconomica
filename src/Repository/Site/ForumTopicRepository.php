<?php

namespace App\Repository\Site;

use App\Entity\Site\ForumCategory;
use App\Entity\Site\ForumTopic;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ForumTopic>
 */
final class ForumTopicRepository extends EntityRepository
{
    /** @return list<ForumTopic> */
    public function findVisibleLatest(?ForumCategory $category = null, int $limit = 60): array
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.isHidden = false')
            ->orderBy('t.isPinned', 'DESC')
            ->addOrderBy('t.lastReplyAt', 'DESC')
            ->setMaxResults($limit);

        if ($category !== null) {
            $qb->andWhere('t.category = :category')->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    public function findVisibleBySlug(string $slug): ?ForumTopic
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.slug = :slug')
            ->andWhere('t.isHidden = false')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<ForumTopic> */
    public function findRecentForAdmin(int $limit = 80): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.lastReplyAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUserId(int $userId): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ForumTopic> */
    public function findByUserId(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
