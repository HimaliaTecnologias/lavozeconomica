<?php

namespace App\Ingestion\HimtecMedia;

final readonly class HimtecMediaFetchResult
{
    public function __construct(
        public int $received = 0,
        public int $duplicates = 0,
        public int $published = 0,
        public int $scheduled = 0,
        public int $drafts = 0,
        public int $errors = 0,
        /** @var list<string> */
        public array $errorMessages = [],
        public int $feedTotal = 0,
        /** @var list<HimtecMediaFetchItemResult> */
        public array $items = [],
        public ?int $runId = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->errors === 0;
    }

    public function summary(): string
    {
        if ($this->feedTotal === 0 && $this->errors === 0) {
            return '0 noticias en el feed (respuesta vacía)';
        }

        return sprintf(
            '%d en feed, %d nuevas, %d publicadas, %d programadas, %d borrador (revisar imagen), %d duplicadas, %d errores',
            $this->feedTotal,
            $this->received,
            $this->published,
            $this->scheduled,
            $this->drafts,
            $this->duplicates,
            $this->errors,
        );
    }

    public function withRunId(int $runId): self
    {
        return new self(
            received: $this->received,
            duplicates: $this->duplicates,
            published: $this->published,
            scheduled: $this->scheduled,
            drafts: $this->drafts,
            errors: $this->errors,
            errorMessages: $this->errorMessages,
            feedTotal: $this->feedTotal,
            items: $this->items,
            runId: $runId,
        );
    }
}
