<?php

namespace App\Tv;

use App\Core\Site\Dto\TvChannelScheduleView;
use App\Core\Site\Dto\TvConfig;
use App\Core\Site\Dto\TvProgramView;
use App\Core\Site\Dto\TvScheduleView;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class TvProgrammingService
{
    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly HttpClientInterface $httpClient,
        private readonly TvXmltvParser $xmltvParser,
    ) {}

    public function getConfig(SiteContext $context): TvConfig
    {
        return $this->settingsReader->getTvConfig($context);
    }

    public function getSchedule(SiteContext $context, bool $forceRefresh = false): ?TvScheduleView
    {
        $config = $this->getConfig($context);
        if (!$config->enabled || !$config->isConfigured()) {
            return null;
        }

        if (!$forceRefresh && $this->isCacheFresh($config)) {
            $cached = $this->loadCachedSchedule($config);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $schedule = $this->fetchSchedule($config);
            $this->persistCache($context, $schedule);

            return $schedule;
        } catch (\Throwable) {
            return $this->loadCachedSchedule($config);
        }
    }

    private function fetchSchedule(TvConfig $config): TvScheduleView
    {
        $timezone = $this->safeTimezone($config->timezone);
        $now = new \DateTimeImmutable('now', $timezone);
        $dayStart = $now->setTime(0, 0, 0);
        $dayEnd = $dayStart->modify('+1 day');

        $definitions = [];
        foreach ($config->channelIds as $channelId) {
            $definition = TvChannelRegistry::find($channelId);
            if ($definition !== null) {
                $definitions[] = $definition;
            }
        }

        if ($definitions === []) {
            throw new \RuntimeException('No hay canales configurados.');
        }

        $response = $this->httpClient->request('GET', $config->feedUrl, [
            'timeout' => 120,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; MyMediaCMS/1.0)',
                'Accept' => 'application/xml, text/xml, */*',
            ],
        ]);

        $xml = $response->getContent();
        $channels = $this->xmltvParser->parse($xml, $definitions, $dayStart, $dayEnd);

        return new TvScheduleView(
            channels: $channels,
            fetchedAt: new \DateTimeImmutable(),
            scheduleDate: $dayStart,
            timezone: $timezone->getName(),
        );
    }

    private function isCacheFresh(TvConfig $config): bool
    {
        if ($config->cachedAt === null) {
            return false;
        }

        return $config->cachedAt->getTimestamp() + self::CACHE_TTL_SECONDS > time();
    }

    private function loadCachedSchedule(TvConfig $config): ?TvScheduleView
    {
        $raw = $config->cachedJson;
        if ($raw === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $channelsRaw = $data['channels'] ?? [];
        if (!is_array($channelsRaw)) {
            return null;
        }

        $timezone = $this->safeTimezone((string) ($data['timezone'] ?? $config->timezone));
        $channels = [];
        foreach ($channelsRaw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $programmesRaw = $row['programmes'] ?? [];
            if (!is_array($programmesRaw)) {
                $programmesRaw = [];
            }

            $programmes = [];
            foreach ($programmesRaw as $programmeRow) {
                if (!is_array($programmeRow)) {
                    continue;
                }

                try {
                    $startsAt = isset($programmeRow['startsAt']) && is_string($programmeRow['startsAt'])
                        ? new \DateTimeImmutable($programmeRow['startsAt'])
                        : null;
                    $endsAt = isset($programmeRow['endsAt']) && is_string($programmeRow['endsAt'])
                        ? new \DateTimeImmutable($programmeRow['endsAt'])
                        : null;
                } catch (\Throwable) {
                    $startsAt = null;
                    $endsAt = null;
                }

                if ($startsAt === null || $endsAt === null) {
                    continue;
                }

                $programmes[] = new TvProgramView(
                    title: (string) ($programmeRow['title'] ?? ''),
                    description: (string) ($programmeRow['description'] ?? ''),
                    category: (string) ($programmeRow['category'] ?? ''),
                    episode: (string) ($programmeRow['episode'] ?? ''),
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                );
            }

            $channels[] = new TvChannelScheduleView(
                id: (string) ($row['id'] ?? ''),
                name: (string) ($row['name'] ?? ''),
                xmltvId: (string) ($row['xmltvId'] ?? ''),
                group: (string) ($row['group'] ?? ''),
                iconUrl: (string) ($row['iconUrl'] ?? ''),
                programmes: $programmes,
            );
        }

        $fetchedAt = isset($data['fetchedAt']) && is_string($data['fetchedAt'])
            ? new \DateTimeImmutable($data['fetchedAt'])
            : ($config->cachedAt ?? new \DateTimeImmutable());

        $scheduleDate = isset($data['scheduleDate']) && is_string($data['scheduleDate'])
            ? new \DateTimeImmutable($data['scheduleDate'])
            : new \DateTimeImmutable('today', $timezone);

        return new TvScheduleView(
            channels: $channels,
            fetchedAt: $fetchedAt,
            scheduleDate: $scheduleDate,
            timezone: (string) ($data['timezone'] ?? $timezone->getName()),
        );
    }

    private function persistCache(SiteContext $context, TvScheduleView $schedule): void
    {
        $channels = [];
        foreach ($schedule->channels as $channel) {
            $programmes = [];
            foreach ($channel->programmes as $programme) {
                $programmes[] = [
                    'title' => $programme->title,
                    'description' => $programme->description,
                    'category' => $programme->category,
                    'episode' => $programme->episode,
                    'startsAt' => $programme->startsAt->format(\DateTimeInterface::ATOM),
                    'endsAt' => $programme->endsAt->format(\DateTimeInterface::ATOM),
                ];
            }

            $channels[] = [
                'id' => $channel->id,
                'name' => $channel->name,
                'xmltvId' => $channel->xmltvId,
                'group' => $channel->group,
                'iconUrl' => $channel->iconUrl,
                'programmes' => $programmes,
            ];
        }

        $payload = json_encode([
            'channels' => $channels,
            'fetchedAt' => $schedule->fetchedAt->format(\DateTimeInterface::ATOM),
            'scheduleDate' => $schedule->scheduleDate->format(\DateTimeInterface::ATOM),
            'timezone' => $schedule->timezone,
        ], JSON_THROW_ON_ERROR);

        $this->settingsWriter->setMany($context, [
            ['tv', 'cached_json', $payload],
            ['tv', 'cached_at', $schedule->fetchedAt->format(\DateTimeInterface::ATOM)],
        ]);
    }

    private function safeTimezone(string $timezone): \DateTimeZone
    {
        try {
            return new \DateTimeZone($timezone !== '' ? $timezone : 'Europe/Madrid');
        } catch (\Throwable) {
            return new \DateTimeZone('Europe/Madrid');
        }
    }
}
