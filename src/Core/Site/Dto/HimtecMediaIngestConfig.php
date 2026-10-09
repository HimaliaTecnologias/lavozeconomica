<?php

namespace App\Core\Site\Dto;

final readonly class HimtecMediaIngestConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $jsonUrl = '',
        public string $apiKey = '',
        public int $intervalMinutes = 60,
        public ?\DateTimeImmutable $lastFetchedAt = null,
        public string $lastFetchStatus = '',
        public int $lastFetchCount = 0,
        public int $lastFetchDuplicates = 0,
        public int $publishStaggerMinutes = 0,
    ) {}

    public function isDue(): bool
    {
        if (!$this->enabled || $this->jsonUrl === '' || $this->apiKey === '') {
            return false;
        }

        if ($this->lastFetchedAt === null) {
            return true;
        }

        $next = $this->lastFetchedAt->modify(sprintf('+%d minutes', $this->intervalMinutes));

        return $next <= new \DateTimeImmutable();
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function usesStaggeredPublish(): bool
    {
        return $this->publishStaggerMinutes > 0;
    }
}
