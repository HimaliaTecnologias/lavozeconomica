<?php

namespace App\Entity\Platform;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_domains')]
class SiteDomain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class, inversedBy: 'domains')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 255, unique: true)]
    private string $domain;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPrimary = false;

    public function __construct(Site $site, string $domain, bool $isPrimary = false)
    {
        $this->site = $site;
        $this->domain = strtolower($domain);
        $this->isPrimary = $isPrimary;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function setSite(Site $site): self
    {
        $this->site = $site;

        return $this;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): self
    {
        $this->domain = strtolower($domain);

        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }
}
