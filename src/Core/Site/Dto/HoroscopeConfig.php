<?php

namespace App\Core\Site\Dto;

final readonly class HoroscopeConfig
{
    public function __construct(
        public bool $enabled = false,
        public bool $aiEnabled = false,
        public string $openaiApiKey = '',
        public bool $hasOpenaiApiKey = false,
        public string $dailyJson = '',
        public ?\DateTimeImmutable $dailyCachedAt = null,
        public string $dailyDateKey = '',
    ) {}

    public function isConfigured(): bool
    {
        return $this->enabled;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'aiEnabled' => $this->aiEnabled,
            'hasOpenaiApiKey' => $this->hasOpenaiApiKey,
        ];
    }
}
