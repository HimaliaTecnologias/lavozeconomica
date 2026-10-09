<?php

namespace App\Editorial;

use App\Entity\Site\AdBanner;
use App\Entity\Site\AdBannerClick;
use App\Repository\Site\AdBannerClickRepository;
use Doctrine\ORM\EntityManagerInterface;

final class AdClickRecorder
{
    public function record(AdBanner $banner, ?string $pagePath, EntityManagerInterface $em, ?string $placement = null): void
    {
        $click = new AdBannerClick($banner, $pagePath, $placement);
        $banner->incrementClickCount();
        $em->persist($click);
        $em->flush();
    }

    public function clickRepository(EntityManagerInterface $em): AdBannerClickRepository
    {
        $repo = $em->getRepository(AdBannerClick::class);
        if (!$repo instanceof AdBannerClickRepository) {
            throw new \RuntimeException('AdBannerClickRepository no disponible.');
        }

        return $repo;
    }
}
