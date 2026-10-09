<?php

namespace App\Ingestion\HimtecMedia;

final readonly class HimtecMediaFetchItemResult
{
    public function __construct(
        public string $externalId,
        public string $title,
        /** new | duplicate | error */
        public string $outcome,
        public ?int $ingestedStoryId = null,
        public ?int $articleId = null,
        public bool $published = false,
        public bool $scheduled = false,
        public bool $draft = false,
        public ?\DateTimeImmutable $scheduledAt = null,
        public ?string $error = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'externalId' => $this->externalId,
            'title' => $this->title,
            'outcome' => $this->outcome,
            'ingestedStoryId' => $this->ingestedStoryId,
            'articleId' => $this->articleId,
            'published' => $this->published,
            'scheduled' => $this->scheduled,
            'draft' => $this->draft,
            'scheduledAt' => $this->scheduledAt?->format(\DateTimeInterface::ATOM),
            'error' => $this->error,
        ];
    }
}
