<?php

namespace App\Entity\Site;

use App\Repository\Site\AdminActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminActivityLogRepository::class)]
#[ORM\Table(name: 'admin_activity_logs')]
#[ORM\Index(columns: ['created_at'], name: 'idx_admin_activity_created')]
class AdminActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(length: 120)]
    private string $userName;

    #[ORM\Column(length: 64)]
    private string $action;

    #[ORM\Column(name: 'entity_type', length: 64, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(name: 'entity_id', nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(name: 'entity_label', length: 255, nullable: true)]
    private ?string $entityLabel = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $userId,
        string $userName,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityLabel = null,
    ) {
        $this->userId = $userId;
        $this->userName = $userName;
        $this->action = $action;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->entityLabel = $entityLabel;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
