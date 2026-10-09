<?php

namespace App\Core\Site\Dto;

final readonly class WeatherConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $postalCode = '',
        public string $placeName = '',
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?\DateTimeImmutable $cachedAt = null,
        public string $cachedJson = '',
        public WeatherSponsorConfig $sponsor = new WeatherSponsorConfig(),
        public bool $aiSummaryEnabled = false,
        public string $openaiApiKey = '',
        public bool $hasOpenaiApiKey = false,
        public string $aiSummaryJson = '',
        public ?\DateTimeImmutable $aiSummaryCachedAt = null,
        public ?\DateTimeImmutable $aiSummaryForecastAt = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->postalCode !== '' && $this->latitude !== null && $this->longitude !== null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'postalCode' => $this->postalCode,
            'placeName' => $this->placeName,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'cachedAt' => $this->cachedAt?->format(\DateTimeInterface::ATOM),
            'sponsor' => $this->sponsor->toArray(),
            'aiSummaryEnabled' => $this->aiSummaryEnabled,
            'hasOpenaiApiKey' => $this->hasOpenaiApiKey,
        ];
    }

    public function hasOpenaiKeyConfigured(): bool
    {
        return $this->hasOpenaiApiKey;
    }
}
