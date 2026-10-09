<?php

namespace App\Security;

use App\Entity\Platform\Site;
use App\Entity\Platform\UserSiteRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UserSiteRoleQuery
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {}

    /** @return list<UserSiteRole> */
    public function findStaffForSite(Site $site): array
    {
        return $this->platformEm->createQueryBuilder()
            ->select('r', 'u')
            ->from(UserSiteRole::class, 'r')
            ->innerJoin('r.user', 'u')
            ->andWhere('r.site = :site')
            ->andWhere('r.role IN (:roles)')
            ->setParameter('site', $site)
            ->setParameter('roles', SiteRole::STAFF_ROLES)
            ->orderBy('u.name', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
