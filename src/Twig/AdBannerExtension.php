<?php

namespace App\Twig;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\AdBanner;
use App\Entity\Site\Enum\AdPlacement;
use App\Repository\Site\AdBannerRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdBannerExtension extends AbstractExtension
{
    /** @var list<AdBanner>|null */
    private ?array $activeBanners = null;

    public function __construct(
        private readonly SiteContextHolder $siteHolder,
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('ad_banners', $this->getBannersForPlacement(...)),
        ];
    }

    /**
     * @param list<int>|int|string|null $tagIds
     *
     * @return list<AdBanner>
     */
    public function getBannersForPlacement(
        string $placement,
        int|string|null $categoryId = null,
        array|int|string|null $tagIds = null,
    ): array {
        $site = $this->siteHolder->get();
        if ($site === null) {
            return [];
        }

        $enum = AdPlacement::tryFrom($placement);
        if ($enum === null) {
            return [];
        }

        $resolvedCategoryId = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
        if ($resolvedCategoryId !== null && $resolvedCategoryId <= 0) {
            $resolvedCategoryId = null;
        }

        $resolvedTagIds = [];
        if (\is_array($tagIds)) {
            foreach ($tagIds as $tagId) {
                $id = (int) $tagId;
                if ($id > 0) {
                    $resolvedTagIds[] = $id;
                }
            }
        } elseif ($tagIds !== null && $tagIds !== '') {
            $id = (int) $tagIds;
            if ($id > 0) {
                $resolvedTagIds[] = $id;
            }
        }

        $matched = [];
        foreach ($this->loadActiveBanners($site) as $banner) {
            if (!$banner->hasPlacement($enum)) {
                continue;
            }
            if (!$banner->matchesTargeting($resolvedCategoryId, $resolvedTagIds)) {
                continue;
            }
            $matched[] = $banner;
        }

        return $matched;
    }

    /**
     * @return list<AdBanner>
     */
    private function loadActiveBanners(\App\Core\Site\SiteContext $site): array
    {
        if ($this->activeBanners !== null) {
            return $this->activeBanners;
        }

        try {
            $em = $this->siteEmFactory->forContext($site);
            $repo = $em->getRepository(AdBanner::class);
            $this->activeBanners = $repo instanceof AdBannerRepository
                ? $repo->findAllActive()
                : [];
        } catch (\Throwable) {
            // Nunca tumbar la página por publicidad / proxies / BD sitio.
            $this->activeBanners = [];
        }

        return $this->activeBanners;
    }
}
