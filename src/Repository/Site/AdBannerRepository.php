<?php

namespace App\Repository\Site;

use App\Entity\Site\AdBanner;
use App\Entity\Site\Enum\AdPlacement;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<AdBanner>
 */
final class AdBannerRepository extends EntityRepository
{
    /**
     * @return list<AdBanner>
     */
    public function findAllActive(): array
    {
        /** @var list<AdBanner> $banners */
        $banners = $this->createQueryBuilder('b')
            ->where('b.isActive = true')
            ->orderBy('b.sortOrder', 'ASC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter(
            $banners,
            static fn (AdBanner $banner): bool => $banner->isRenderable(),
        ));
    }

    /**
     * @return list<AdBanner>
     */
    public function findActiveForPlacement(AdPlacement $placement): array
    {
        return array_values(array_filter(
            $this->findAllActive(),
            static fn (AdBanner $banner): bool => $banner->hasPlacement($placement),
        ));
    }

    /** @return array<string, list<AdBanner>> */
    public function findAllActiveGrouped(): array
    {
        $grouped = [];
        foreach (AdPlacement::cases() as $placement) {
            $grouped[$placement->value] = [];
        }

        foreach ($this->findAllActive() as $banner) {
            foreach ($banner->getPlacements() as $placement) {
                $grouped[$placement->value][] = $banner;
            }
        }

        return $grouped;
    }

    /**
     * Rellena placements_json a partir de la ubicación legacy si hace falta.
     */
    public function backfillPlacementsJson(): int
    {
        $updated = 0;
        /** @var list<AdBanner> $banners */
        $banners = $this->findAll();
        foreach ($banners as $banner) {
            $banner->setPlacements($banner->getPlacements());
            ++$updated;
        }
        $this->getEntityManager()->flush();

        return $updated;
    }
}
