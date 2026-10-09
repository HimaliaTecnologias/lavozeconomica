<?php

namespace App\Entity\Site;

use App\Entity\Site\Enum\SiteLinkGroup;
use App\Repository\Site\SiteLinkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteLinkRepository::class)]
#[ORM\Table(name: 'site_links')]
#[ORM\Index(columns: ['group', 'is_enabled', 'sort_order'], name: 'idx_site_links_group')]
class SiteLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(length: 2048)]
    private string $url;

    // `group` is reserved in MariaDB; backticks tell Doctrine to quote it in SQL.
    #[ORM\Column(name: '`group`', length: 32, enumType: SiteLinkGroup::class)]
    private SiteLinkGroup $group;

    #[ORM\Column(name: 'logo_url', length: 2048, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'is_enabled', options: ['default' => true])]
    private bool $isEnabled = true;

    #[ORM\Column(name: 'show_in_footer', options: ['default' => true])]
    private bool $showInFooter = true;

    #[ORM\Column(name: 'show_in_sidebar', options: ['default' => false])]
    private bool $showInSidebar = false;

    public function __construct(string $title, string $url, SiteLinkGroup $group)
    {
        $this->title = $title;
        $this->url = $url;
        $this->group = $group;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getGroup(): SiteLinkGroup
    {
        return $this->group;
    }

    public function setGroup(SiteLinkGroup $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): self
    {
        $this->logoUrl = $logoUrl;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setIsEnabled(bool $isEnabled): self
    {
        $this->isEnabled = $isEnabled;

        return $this;
    }

    public function isShowInFooter(): bool
    {
        return $this->showInFooter;
    }

    public function setShowInFooter(bool $showInFooter): self
    {
        $this->showInFooter = $showInFooter;

        return $this;
    }

    public function isShowInSidebar(): bool
    {
        return $this->showInSidebar;
    }

    public function setShowInSidebar(bool $showInSidebar): self
    {
        $this->showInSidebar = $showInSidebar;

        return $this;
    }
}
