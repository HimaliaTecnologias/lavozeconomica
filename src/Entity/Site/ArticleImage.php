<?php

namespace App\Entity\Site;

use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'article_images')]
#[ORM\Index(columns: ['article_id', 'sort_order'], name: 'idx_article_image_order')]
class ArticleImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Article::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'article_id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\Column(name: 'is_featured', options: ['default' => false])]
    private bool $isFeatured = false;

    #[ORM\Column(name: 'source_url', length: 2048, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(name: 'enhancement_status', length: 32, enumType: ArticleImageEnhancementStatus::class, options: ['default' => 'pending'])]
    private ArticleImageEnhancementStatus $enhancementStatus = ArticleImageEnhancementStatus::Pending;

    #[ORM\Column(name: 'enhanced_at', nullable: true)]
    private ?\DateTimeImmutable $enhancedAt = null;

    #[ORM\Column(name: 'enhancement_error', length: 512, nullable: true)]
    private ?string $enhancementError = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'media_id', nullable: true, onDelete: 'SET NULL')]
    private ?Media $media = null;

    public function __construct(Article $article, string $url, int $sortOrder = 0)
    {
        $this->article = $article;
        $this->url = $url;
        $this->sortOrder = $sortOrder;
        $this->enhancementStatus = ArticleImageEnhancementStatus::Pending;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArticle(): Article
    {
        return $this->article;
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

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;

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

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): self
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function getEnhancementStatus(): ArticleImageEnhancementStatus
    {
        return $this->enhancementStatus;
    }

    public function setEnhancementStatus(ArticleImageEnhancementStatus $enhancementStatus): self
    {
        $this->enhancementStatus = $enhancementStatus;

        return $this;
    }

    public function getEnhancedAt(): ?\DateTimeImmutable
    {
        return $this->enhancedAt;
    }

    public function setEnhancedAt(?\DateTimeImmutable $enhancedAt): self
    {
        $this->enhancedAt = $enhancedAt;

        return $this;
    }

    public function getEnhancementError(): ?string
    {
        return $this->enhancementError;
    }

    public function setEnhancementError(?string $enhancementError): self
    {
        $this->enhancementError = $enhancementError;

        return $this;
    }

    public function markPendingEnhancement(): self
    {
        $this->enhancementStatus = ArticleImageEnhancementStatus::Pending;
        $this->enhancedAt = null;
        $this->enhancementError = null;
        $this->width = null;
        $this->height = null;
        $this->sourceUrl = null;
        $this->media = null;

        return $this;
    }

    public function getMedia(): ?Media
    {
        return $this->media;
    }

    public function setMedia(?Media $media): self
    {
        $this->media = $media;

        return $this;
    }

    public function applyFromLibraryMedia(Media $media): self
    {
        $this->media = $media;
        $this->url = $media->getPublicPath();
        $this->width = $media->getWidth();
        $this->height = $media->getHeight();
        $this->enhancementStatus = $media->getEnhancementStatus();
        $this->enhancedAt = $media->getEnhancedAt();
        $this->enhancementError = $media->getEnhancementError();
        if ($media->getAlt() !== null) {
            $this->alt = $media->getAlt();
        }
        if ($media->getCaption() !== null) {
            $this->caption = $media->getCaption();
        }

        return $this;
    }
}
