<?php

namespace App\Entity\Platform;

use App\Entity\Platform\Enum\SiteStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sites')]
#[ORM\HasLifecycleCallbacks]
class Site
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $code;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 16, enumType: SiteStatus::class)]
    private SiteStatus $status = SiteStatus::Active;

    #[ORM\Column(length: 8, options: ['default' => 'es'])]
    private string $defaultLocale = 'es';

    #[ORM\Column(length: 64, options: ['default' => 'Europe/Madrid'])]
    private string $timezone = 'Europe/Madrid';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, SiteDomain> */
    #[ORM\OneToMany(targetEntity: SiteDomain::class, mappedBy: 'site', cascade: ['persist'], orphanRemoval: true)]
    private Collection $domains;

    #[ORM\OneToOne(targetEntity: SiteDatabaseConfig::class, mappedBy: 'site', cascade: ['persist', 'remove'])]
    private ?SiteDatabaseConfig $databaseConfig = null;

    public function __construct(string $code, string $name)
    {
        $this->code = $code;
        $this->name = $name;
        $this->domains = new ArrayCollection();
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getStatus(): SiteStatus
    {
        return $this->status;
    }

    public function setStatus(SiteStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    public function setDefaultLocale(string $defaultLocale): self
    {
        $this->defaultLocale = $defaultLocale;

        return $this;
    }

    public function getTimezone(): string
    {
        return $this->timezone;
    }

    public function setTimezone(string $timezone): self
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, SiteDomain> */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function addDomain(SiteDomain $domain): self
    {
        if (!$this->domains->contains($domain)) {
            $this->domains->add($domain);
            $domain->setSite($this);
        }

        return $this;
    }

    public function getDatabaseConfig(): ?SiteDatabaseConfig
    {
        return $this->databaseConfig;
    }

    public function setDatabaseConfig(SiteDatabaseConfig $databaseConfig): self
    {
        if ($databaseConfig->getSite() !== $this) {
            $databaseConfig->setSite($this);
        }
        $this->databaseConfig = $databaseConfig;

        return $this;
    }
}
