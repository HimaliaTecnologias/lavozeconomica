<?php

namespace App\Core\Site\Dto;

final readonly class GamesConfig
{
    public function __construct(
        public bool $enabled = false,
        public bool $sudokuEnabled = true,
        public bool $wordScrambleEnabled = true,
        public string $sudokuCachedJson = '',
        public string $sudokuCachedDate = '',
        public string $wordScrambleCachedJson = '',
        public string $wordScrambleCachedDate = '',
    ) {}

    public function isConfigured(): bool
    {
        return $this->enabled && ($this->sudokuEnabled || $this->wordScrambleEnabled);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'sudokuEnabled' => $this->sudokuEnabled,
            'wordScrambleEnabled' => $this->wordScrambleEnabled,
        ];
    }
}
