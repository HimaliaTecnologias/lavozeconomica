<?php

namespace App\Core\Site\Dto;

use App\Tv\TvChannelRegistry;

final readonly class TvConfig
{
    public const DEFAULT_FEED_URL = 'https://iptv-epg.org/files/epg-es.xml';

    /**
     * @param list<string> $channelIds
     */
    public function __construct(
        public bool $enabled = false,
        public array $channelIds = TvChannelRegistry::DEFAULT_CHANNEL_IDS,
        public string $feedUrl = self::DEFAULT_FEED_URL,
        public string $timezone = 'Europe/Madrid',
        public ?\DateTimeImmutable $cachedAt = null,
        public string $cachedJson = '',
    ) {}

    public function isConfigured(): bool
    {
        return $this->channelIds !== [] && $this->feedUrl !== '';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'channelIds' => $this->channelIds,
            'feedUrl' => $this->feedUrl,
            'timezone' => $this->timezone,
            'cachedAt' => $this->cachedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
