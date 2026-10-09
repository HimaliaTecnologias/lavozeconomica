<?php

namespace App\Entity\Platform;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ingest_fetch_runs')]
#[ORM\Index(columns: ['site_id', 'ran_at'], name: 'idx_ingest_fetch_site_ran')]
class IngestFetchRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Site::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Site $site;

    #[ORM\Column(length: 64, options: ['default' => 'himtecmedia'])]
    private string $source = 'himtecmedia';

    #[ORM\Column(name: 'trigger_type', length: 32)]
    private string $trigger;

    #[ORM\Column]
    private \DateTimeImmutable $ranAt;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $requestUrl = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $feedTotal = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $received = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $duplicates = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $published = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $errors = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $success = true;

    #[ORM\Column(type: 'text')]
    private string $statusMessage;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $errorMessages = [];

    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')]
    private array $items = [];

    public function __construct(
        Site $site,
        string $trigger,
        string $statusMessage,
        bool $success = true,
    ) {
        $this->site = $site;
        $this->trigger = $trigger;
        $this->ranAt = new \DateTimeImmutable();
        $this->statusMessage = $statusMessage;
        $this->success = $success;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getTrigger(): string
    {
        return $this->trigger;
    }

    public function getRanAt(): \DateTimeImmutable
    {
        return $this->ranAt;
    }

    public function getRequestUrl(): ?string
    {
        return $this->requestUrl;
    }

    public function setRequestUrl(?string $requestUrl): self
    {
        $this->requestUrl = $requestUrl;

        return $this;
    }

    public function getFeedTotal(): int
    {
        return $this->feedTotal;
    }

    public function setFeedTotal(int $feedTotal): self
    {
        $this->feedTotal = $feedTotal;

        return $this;
    }

    public function getReceived(): int
    {
        return $this->received;
    }

    public function setReceived(int $received): self
    {
        $this->received = $received;

        return $this;
    }

    public function getDuplicates(): int
    {
        return $this->duplicates;
    }

    public function setDuplicates(int $duplicates): self
    {
        $this->duplicates = $duplicates;

        return $this;
    }

    public function getPublished(): int
    {
        return $this->published;
    }

    public function setPublished(int $published): self
    {
        $this->published = $published;

        return $this;
    }

    public function getErrors(): int
    {
        return $this->errors;
    }

    public function setErrors(int $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;

        return $this;
    }

    public function getStatusMessage(): string
    {
        return $this->statusMessage;
    }

    public function setStatusMessage(string $statusMessage): self
    {
        $this->statusMessage = $statusMessage;

        return $this;
    }

    /** @return list<string> */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    /** @param list<string> $errorMessages */
    public function setErrorMessages(array $errorMessages): self
    {
        $this->errorMessages = $errorMessages;

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function getItems(): array
    {
        return $this->items;
    }

    /** @param list<array<string, mixed>> $items */
    public function setItems(array $items): self
    {
        $this->items = $items;

        return $this;
    }
}
