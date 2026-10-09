<?php

namespace App\Entity\Platform;

use Doctrine\ORM\Mapping as ORM;

/** Estado global de la plataforma (singleton, id=1). */
#[ORM\Entity]
#[ORM\Table(name: 'platform_state')]
class PlatformState
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $installedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $primarySiteCode = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function isInstalled(): bool
    {
        return $this->installedAt !== null;
    }

    public function getInstalledAt(): ?\DateTimeImmutable
    {
        return $this->installedAt;
    }

    public function getPrimarySiteCode(): ?string
    {
        return $this->primarySiteCode;
    }

    public function markInstalled(string $primarySiteCode): void
    {
        $this->installedAt = new \DateTimeImmutable();
        $this->primarySiteCode = $primarySiteCode;
    }
}
