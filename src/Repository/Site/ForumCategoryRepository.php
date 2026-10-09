<?php

namespace App\Repository\Site;

use App\Entity\Site\ForumCategory;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ForumCategory>
 */
final class ForumCategoryRepository extends EntityRepository
{
    /** @return list<ForumCategory> */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = true')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

  /** @return list<ForumCategory> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveBySlug(string $slug): ?ForumCategory
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
