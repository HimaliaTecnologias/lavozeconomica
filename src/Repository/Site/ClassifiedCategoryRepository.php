<?php

namespace App\Repository\Site;

use App\Entity\Site\ClassifiedCategory;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ClassifiedCategory>
 */
final class ClassifiedCategoryRepository extends EntityRepository
{
    /** @return list<ClassifiedCategory> */
    public function findActiveOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = true')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ClassifiedCategory> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.sortOrder', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findActiveBySlug(string $slug): ?ClassifiedCategory
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.slug = :slug')
            ->andWhere('c.isActive = true')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
