<?php

namespace App\Repository\Site;

use App\Entity\Site\Enum\SiteLinkGroup;
use App\Entity\Site\SiteLink;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<SiteLink>
 */
final class SiteLinkRepository extends EntityRepository
{
    /** @return list<SiteLink> */
    public function findEnabledForFooter(SiteLinkGroup $group): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.group = :group')
            ->andWhere('l.isEnabled = true')
            ->andWhere('l.showInFooter = true')
            ->setParameter('group', $group)
            ->orderBy('l.sortOrder', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<SiteLink> */
    public function findEnabledForSidebar(SiteLinkGroup $group): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.group = :group')
            ->andWhere('l.isEnabled = true')
            ->andWhere('l.showInSidebar = true')
            ->setParameter('group', $group)
            ->orderBy('l.sortOrder', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<SiteLink> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.group', 'ASC')
            ->addOrderBy('l.sortOrder', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
