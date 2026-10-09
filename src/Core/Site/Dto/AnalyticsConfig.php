<?php

namespace App\Core\Site\Dto;

final readonly class AnalyticsConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $headCode = '',
        public string $bodyCode = '',
    ) {}

    public function isActive(): bool
    {
        return $this->enabled && ($this->headCode !== '' || $this->bodyCode !== '');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'headCode' => $this->headCode,
            'bodyCode' => $this->bodyCode,
        ];
    }
}
