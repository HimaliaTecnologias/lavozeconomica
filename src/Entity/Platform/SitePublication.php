<?php

namespace App\Entity\Platform;

use App\Entity\Platform\Enum\SitePublicationStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'site_publications')]
#[ORM\UniqueConstraint(name: 'uniq_site_ingested', columns: ['site_id', 'ingested_story_id'])]
class SitePublication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\ManyToOne(targetEntity: IngestedStory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private IngestedStory $ingestedStory;

    #[ORM\Column(nullable: true)]
    private ?int $articleId = null;

    #[ORM\Column(length: 32, enumType: SitePublicationStatus::class)]
    private SitePublicationStatus $status;

    #[ORM\Column(options: ['default' => false])]
    private bool $autoPublished = false;

    #[ORM\Column]
    private \DateTimeImmutable $assignedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    public function __construct(Site $site, IngestedStory $ingestedStory, SitePublicationStatus $status)
    {
        $this->site = $site;
        $this->ingestedStory = $ingestedStory;
        $this->status = $status;
        $this->assignedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getIngestedStory(): IngestedStory
    {
        return $this->ingestedStory;
    }

    public function getArticleId(): ?int
    {
        return $this->articleId;
    }

    public function setArticleId(?int $articleId): self
    {
        $this->articleId = $articleId;

        return $this;
    }

    public function getStatus(): SitePublicationStatus
    {
        return $this->status;
    }

    public function setStatus(SitePublicationStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setAutoPublished(bool $autoPublished): self
    {
        $this->autoPublished = $autoPublished;

        return $this;
    }

    public function markPublished(): self
    {
        $this->status = SitePublicationStatus::Published;
        $this->publishedAt = new \DateTimeImmutable();

        return $this;
    }
}
