<?php

namespace App\Core\Site\Dto;

final readonly class ClassifiedsConfig
{
    public function __construct(
        public bool $enabled = false,
    ) {}

    public function isConfigured(): bool
    {
        return $this->enabled;
    }

    /** @return array<string, bool> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
        ];
    }
}
