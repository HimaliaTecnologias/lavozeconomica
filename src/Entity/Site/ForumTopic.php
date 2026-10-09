<?php

namespace App\Entity\Site;

use App\Repository\Site\ForumTopicRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumTopicRepository::class)]
#[ORM\Table(name: 'forum_topics')]
#[ORM\UniqueConstraint(name: 'uniq_forum_topic_slug', columns: ['slug'])]
#[ORM\Index(columns: ['last_reply_at'], name: 'idx_forum_topic_last_reply')]
class ForumTopic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ForumCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', nullable: true, onDelete: 'RESTRICT')]
    private ?ForumCategory $category = null;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(length: 120)]
    private string $slug;

    #[ORM\Column(type: 'text')]
    private string $body;

    /** ID del usuario en la BD plataforma. */
    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(length: 120)]
    private string $userName;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(name: 'last_reply_at')]
    private \DateTimeImmutable $lastReplyAt;

    #[ORM\Column(name: 'reply_count', options: ['default' => 0])]
    private int $replyCount = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPinned = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isLocked = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $isHidden = false;

    public function __construct(
        string $title,
        string $slug,
        string $body,
        int $userId,
        string $userName,
        ?ForumCategory $category = null,
    ) {
        $this->title = $title;
        $this->slug = $slug;
        $this->body = $body;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->category = $category;
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->lastReplyAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?ForumCategory
    {
        return $this->category;
    }

    public function setCategory(?ForumCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getBody(): string
    {
        return $this->body;
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

    public function getLastReplyAt(): \DateTimeImmutable
    {
        return $this->lastReplyAt;
    }

    public function getReplyCount(): int
    {
        return $this->replyCount;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function isHidden(): bool
    {
        return $this->isHidden;
    }

    public function registerReply(): void
    {
        ++$this->replyCount;
        $now = new \DateTimeImmutable();
        $this->lastReplyAt = $now;
        $this->updatedAt = $now;
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

    public function lock(): self
    {
        $this->isLocked = true;

        return $this;
    }

    public function unlock(): self
    {
        $this->isLocked = false;

        return $this;
    }

    public function pin(): self
    {
        $this->isPinned = true;

        return $this;
    }

    public function unpin(): self
    {
        $this->isPinned = false;

        return $this;
    }
}
