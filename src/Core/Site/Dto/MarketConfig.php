<?php

namespace App\Core\Site\Dto;

use App\Markets\MarketIndexRegistry;

final readonly class MarketConfig
{
    /**
     * @param list<string> $indexIds
     */
    public function __construct(
        public bool $enabled = false,
        public array $indexIds = MarketIndexRegistry::DEFAULT_INDEX_IDS,
        public ?\DateTimeImmutable $cachedAt = null,
        public string $cachedJson = '',
        public bool $aiSummaryEnabled = false,
        public string $openaiApiKey = '',
        public bool $hasOpenaiApiKey = false,
        public string $aiSummaryJson = '',
        public ?\DateTimeImmutable $aiSummaryCachedAt = null,
        public ?\DateTimeImmutable $aiSummaryBoardAt = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->indexIds !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'indexIds' => $this->indexIds,
            'cachedAt' => $this->cachedAt?->format(\DateTimeInterface::ATOM),
            'aiSummaryEnabled' => $this->aiSummaryEnabled,
            'hasOpenaiApiKey' => $this->hasOpenaiApiKey,
        ];
    }
}
