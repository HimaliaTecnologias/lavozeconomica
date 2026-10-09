<?php

namespace App\Entity\Site;

use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Repository\Site\MediaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
#[ORM\Table(name: 'media')]
#[ORM\Index(columns: ['created_at'], name: 'idx_media_created')]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'stored_filename', length: 255)]
    private string $storedFilename;

    #[ORM\Column(name: 'original_filename', length: 512)]
    private string $originalFilename;

    #[ORM\Column(name: 'mime_type', length: 128)]
    private string $mimeType;

    #[ORM\Column(name: 'size_bytes')]
    private int $sizeBytes;

    #[ORM\Column(name: 'public_path', length: 1024)]
    private string $publicPath;

    #[ORM\Column(nullable: true)]
    private ?int $width = null;

    #[ORM\Column(nullable: true)]
    private ?int $height = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(name: 'uploaded_by_user_id', nullable: true)]
    private ?int $uploadedByUserId = null;

    #[ORM\Column(name: 'enhancement_status', length: 32, enumType: ArticleImageEnhancementStatus::class, options: ['default' => 'pending'])]
    private ArticleImageEnhancementStatus $enhancementStatus = ArticleImageEnhancementStatus::Pending;

    #[ORM\Column(name: 'enhanced_at', nullable: true)]
    private ?\DateTimeImmutable $enhancedAt = null;

    #[ORM\Column(name: 'enhancement_error', length: 512, nullable: true)]
    private ?string $enhancementError = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $storedFilename,
        string $originalFilename,
        string $mimeType,
        int $sizeBytes,
        string $publicPath,
    ) {
        $this->storedFilename = $storedFilename;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->sizeBytes = $sizeBytes;
        $this->publicPath = $publicPath;
        $this->createdAt = new \DateTimeImmutable();
        $this->enhancementStatus = ArticleImageEnhancementStatus::Pending;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStoredFilename(): string
    {
        return $this->storedFilename;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    public function setPublicPath(string $publicPath): self
    {
        $this->publicPath = $publicPath;

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

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

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

    public function getUploadedByUserId(): ?int
    {
        return $this->uploadedByUserId;
    }

    public function setUploadedByUserId(?int $uploadedByUserId): self
    {
        $this->uploadedByUserId = $uploadedByUserId;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setSizeBytes(int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;

        return $this;
    }

    public function isVideo(): bool
    {
        return str_starts_with($this->mimeType, 'video/');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }
}
