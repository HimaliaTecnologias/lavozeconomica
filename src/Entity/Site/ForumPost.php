<?php

namespace App\Entity\Site;

use App\Repository\Site\ForumPostRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumPostRepository::class)]
#[ORM\Table(name: 'forum_posts')]
#[ORM\Index(columns: ['topic_id', 'created_at'], name: 'idx_forum_post_topic')]
class ForumPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ForumTopic::class)]
    #[ORM\JoinColumn(name: 'topic_id', nullable: false, onDelete: 'CASCADE')]
    private ForumTopic $topic;

    /** ID del usuario en la BD plataforma. */
    #[ORM\Column(name: 'user_id')]
    private int $userId;

    #[ORM\Column(length: 120)]
    private string $userName;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $isHidden = false;

    public function __construct(ForumTopic $topic, int $userId, string $userName, string $body)
    {
        $this->topic = $topic;
        $this->userId = $userId;
        $this->userName = $userName;
        $this->body = $body;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTopic(): ForumTopic
    {
        return $this->topic;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getUserName(): string
    {
        return $this->userName;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
}
