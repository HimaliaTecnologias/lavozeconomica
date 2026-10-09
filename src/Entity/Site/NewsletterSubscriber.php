<?php

namespace App\Entity\Site;

use App\Entity\Site\Enum\NewsletterFrequency;
use App\Entity\Site\Enum\NewsletterSubscriberStatus;
use App\Repository\Site\NewsletterSubscriberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscribers')]
#[ORM\UniqueConstraint(name: 'uniq_newsletter_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_newsletter_unsub_token', columns: ['unsubscribe_token'])]
#[ORM\Index(columns: ['status', 'frequency'], name: 'idx_newsletter_status_freq')]
class NewsletterSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 32, enumType: NewsletterSubscriberStatus::class)]
    private NewsletterSubscriberStatus $status = NewsletterSubscriberStatus::Pending;

    #[ORM\Column(length: 32, enumType: NewsletterFrequency::class)]
    private NewsletterFrequency $frequency;

    #[ORM\Column(name: 'confirm_token', length: 64, nullable: true)]
    private ?string $confirmToken = null;

    #[ORM\Column(name: 'confirm_token_expires_at', nullable: true)]
    private ?\DateTimeImmutable $confirmTokenExpiresAt = null;

    #[ORM\Column(name: 'unsubscribe_token', length: 64)]
    private string $unsubscribeToken;

    #[ORM\Column(name: 'confirmed_at', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(name: 'unsubscribed_at', nullable: true)]
    private ?\DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(name: 'last_digest_sent_at', nullable: true)]
    private ?\DateTimeImmutable $lastDigestSentAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Category> */
    #[ORM\ManyToMany(targetEntity: Category::class)]
    #[ORM\JoinTable(name: 'newsletter_subscriber_categories')]
    private Collection $categories;

    public function __construct(string $email, NewsletterFrequency $frequency)
    {
        $this->email = strtolower(trim($email));
        $this->frequency = $frequency;
        $this->unsubscribeToken = bin2hex(random_bytes(32));
        $now = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
        $this->categories = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));
        $this->touch();

        return $this;
    }

    public function getStatus(): NewsletterSubscriberStatus
    {
        return $this->status;
    }

    public function getFrequency(): NewsletterFrequency
    {
        return $this->frequency;
    }

    public function setFrequency(NewsletterFrequency $frequency): self
    {
        $this->frequency = $frequency;
        $this->touch();

        return $this;
    }

    public function getConfirmToken(): ?string
    {
        return $this->confirmToken;
    }

    public function getConfirmTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->confirmTokenExpiresAt;
    }

    public function issueConfirmToken(): self
    {
        $this->confirmToken = bin2hex(random_bytes(32));
        $this->confirmTokenExpiresAt = new \DateTimeImmutable('+48 hours');
        $this->status = NewsletterSubscriberStatus::Pending;
        $this->touch();

        return $this;
    }

    public function confirm(): self
    {
        $this->status = NewsletterSubscriberStatus::Confirmed;
        $this->confirmedAt = new \DateTimeImmutable();
        $this->confirmToken = null;
        $this->confirmTokenExpiresAt = null;
        $this->unsubscribedAt = null;
        $this->touch();

        return $this;
    }

    public function unsubscribe(): self
    {
        $this->status = NewsletterSubscriberStatus::Unsubscribed;
        $this->unsubscribedAt = new \DateTimeImmutable();
        $this->touch();

        return $this;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getLastDigestSentAt(): ?\DateTimeImmutable
    {
        return $this->lastDigestSentAt;
    }

    public function markDigestSent(): self
    {
        $this->lastDigestSentAt = new \DateTimeImmutable();
        $this->touch();

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Category> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function syncCategories(array $categories): self
    {
        $this->categories->clear();
        foreach ($categories as $category) {
            if ($category instanceof Category) {
                $this->categories->add($category);
            }
        }
        $this->touch();

        return $this;
    }

    public function isConfirmTokenValid(string $token): bool
    {
        if ($this->confirmToken === null || $this->confirmToken !== $token) {
            return false;
        }

        if ($this->confirmTokenExpiresAt !== null && $this->confirmTokenExpiresAt < new \DateTimeImmutable()) {
            return false;
        }

        return true;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
