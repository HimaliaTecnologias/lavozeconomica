<?php

namespace App\Entity\Site;

use App\Repository\Site\AdBannerClickRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdBannerClickRepository::class)]
#[ORM\Table(name: 'ad_banner_clicks')]
#[ORM\Index(columns: ['clicked_at'], name: 'idx_ad_click_at')]
#[ORM\Index(columns: ['banner_id', 'clicked_at'], name: 'idx_ad_click_banner_at')]
class AdBannerClick
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AdBanner::class)]
    #[ORM\JoinColumn(name: 'banner_id', nullable: false, onDelete: 'CASCADE')]
    private AdBanner $banner;

    #[ORM\Column(name: 'clicked_at')]
    private \DateTimeImmutable $clickedAt;

    #[ORM\Column(name: 'page_path', length: 512, nullable: true)]
    private ?string $pagePath = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $placement = null;

    public function __construct(AdBanner $banner, ?string $pagePath = null, ?string $placement = null)
    {
        $this->banner = $banner;
        $this->clickedAt = new \DateTimeImmutable();
        $this->pagePath = $pagePath !== null && $pagePath !== '' ? $pagePath : null;
        $resolved = $placement !== null && $placement !== '' ? $placement : $banner->getPlacement()->value;
        $this->placement = $banner->hasPlacement($resolved) ? $resolved : $banner->getPlacement()->value;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBanner(): AdBanner
    {
        return $this->banner;
    }

    public function getClickedAt(): \DateTimeImmutable
    {
        return $this->clickedAt;
    }

    public function getPagePath(): ?string
    {
        return $this->pagePath;
    }

    public function getPlacement(): ?string
    {
        return $this->placement;
    }
}
