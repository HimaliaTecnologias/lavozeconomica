<?php

namespace App\Entity\Platform;

use App\Entity\Platform\Enum\IngestedStoryStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'ingested_stories')]
#[ORM\UniqueConstraint(name: 'uniq_source_external', columns: ['source', 'external_id'])]
#[ORM\Index(columns: ['content_hash'], name: 'idx_content_hash')]
#[ORM\Index(columns: ['status', 'received_at'], name: 'idx_status_received')]
class IngestedStory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, options: ['default' => 'himtecmedia'])]
    private string $source = 'himtecmedia';

    #[ORM\Column(length: 255)]
    private string $externalId;

    #[ORM\Column(length: 16)]
    private string $formatVersion;

    #[ORM\Column(length: 512)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $bodyHtml;

    #[ORM\Column(type: 'json')]
    private array $rawPayload;

    #[ORM\Column(length: 64)]
    private string $contentHash;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $geoJson = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $classificationJson = null;

    #[ORM\Column(length: 32, enumType: IngestedStoryStatus::class)]
    private IngestedStoryStatus $status;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct(
        string $externalId,
        string $formatVersion,
        string $title,
        string $bodyHtml,
        array $rawPayload,
        string $contentHash,
        IngestedStoryStatus $status = IngestedStoryStatus::Received,
    ) {
        $this->externalId = $externalId;
        $this->formatVersion = $formatVersion;
        $this->title = $title;
        $this->bodyHtml = $bodyHtml;
        $this->rawPayload = $rawPayload;
        $this->contentHash = $contentHash;
        $this->status = $status;
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getFormatVersion(): string
    {
        return $this->formatVersion;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBodyHtml(): string
    {
        return $this->bodyHtml;
    }

    public function getRawPayload(): array
    {
        return $this->rawPayload;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function getStatus(): IngestedStoryStatus
    {
        return $this->status;
    }

    public function setStatus(IngestedStoryStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function markProcessed(): self
    {
        $this->processedAt = new \DateTimeImmutable();
        $this->status = IngestedStoryStatus::Processed;

        return $this;
    }

    public function getReceivedAt(): \DateTimeImmutable
    {
        return $this->receivedAt;
    }
}
