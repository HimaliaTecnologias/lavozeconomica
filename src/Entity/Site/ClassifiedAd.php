<?php

namespace App\Entity\Site;

use App\Repository\Site\ClassifiedAdRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClassifiedAdRepository::class)]
#[ORM\Table(name: 'classified_ads')]
#[ORM\UniqueConstraint(name: 'uniq_classified_slug', columns: ['slug'])]
#[ORM\Index(columns: ['created_at'], name: 'idx_classified_created')]
#[ORM\Index(columns: ['category_id', 'created_at'], name: 'idx_classified_category_created')]
class ClassifiedAd
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClassifiedCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true, onDelete: 'RESTRICT')]
    private ?ClassifiedCategory $category = null;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(length: 120)]
    private string $slug;

    #[ORM\Column(name: 'price_label', length: 80, nullable: true)]
    private ?string $priceLabel = null;

    #[ORM\Column(name: 'contact_phone', length: 40, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(name: 'contact_email', length: 180, nullable: true)]
    private ?string $contactEmail = null;

    /** ID del usuario en la BD plataforma. */
    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(length: 120)]
    private string $userName;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'expires_at', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isHidden = false;

    public function __construct(
        ClassifiedCategory $category,
        string $title,
        string $body,
        string $slug,
        int $userId,
        string $userName,
    ) {
        $this->category = $category;
        $this->title = $title;
        $this->body = $body;
        $this->slug = $slug;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->expiresAt = $this->createdAt->modify('+60 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?ClassifiedCategory
    {
        return $this->category;
    }

    public function setCategory(ClassifiedCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getPriceLabel(): ?string
    {
        return $this->priceLabel;
    }

    public function setPriceLabel(?string $priceLabel): void
    {
        $this->priceLabel = $priceLabel;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): void
    {
        $this->contactPhone = $contactPhone;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): void
    {
        $this->contactEmail = $contactEmail;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function hide(): self
    {
        $this->isHidden = true;

        return $this;
    }

    public function restore(): self
    {
        $this->isHidden = false;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < new \DateTimeImmutable();
    }
}
