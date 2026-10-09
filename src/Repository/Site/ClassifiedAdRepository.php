<?php

namespace App\Repository\Site;

use App\Entity\Site\ClassifiedAd;
use App\Entity\Site\ClassifiedCategory;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ClassifiedAd>
 */
final class ClassifiedAdRepository extends EntityRepository
{
    /**
     * @return list<ClassifiedAd>
     */
    public function findVisibleLatest(?ClassifiedCategory $category = null, int $limit = 60): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.isHidden = false')
            ->andWhere('a.expiresAt IS NULL OR a.expiresAt >= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($category !== null) {
            $qb->andWhere('a.category = :category')->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    public function findVisibleBySlug(string $slug): ?ClassifiedAd
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.slug = :slug')
            ->andWhere('a.isHidden = false')
            ->andWhere('a.expiresAt IS NULL OR a.expiresAt >= :now')
            ->setParameter('slug', $slug)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<ClassifiedAd> */
    public function findRecentForAdmin(int $limit = 80): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUserId(int $userId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ClassifiedAd> */
    public function findByUserId(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
