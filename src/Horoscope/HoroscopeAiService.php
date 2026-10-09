<?php

namespace App\Horoscope;

use App\Ai\OpenaiApiKeyResolver;
use App\Core\Site\Dto\HoroscopeBoardView;
use App\Core\Site\Dto\HoroscopeConfig;
use App\Core\Site\Dto\HoroscopeSignView;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HoroscopeAiService
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly HoroscopeFallbackContent $fallbackContent,
        private readonly HttpClientInterface $httpClient,
        private readonly OpenaiApiKeyResolver $apiKeyResolver,
    ) {}

    public function getBoard(SiteContext $context, bool $forceRefresh = false): ?HoroscopeBoardView
    {
        $config = $this->settingsReader->getHoroscopeConfig($context);
        if (!$config->enabled) {
            return null;
        }

        $dateKey = $this->todayKey();
        if (!$forceRefresh && $config->dailyDateKey === $dateKey && $config->dailyJson !== '') {
            $cached = $this->loadCached($config, $dateKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $apiKey = $this->apiKeyResolver->resolve($context, $config->openaiApiKey);
        if ($config->aiEnabled && $apiKey !== '') {
            try {
                $model = $this->settingsReader->getAiConfig($context)->model;
                $board = $this->generateWithAi($dateKey, $apiKey, $model);
                $this->persistCache($context, $board, $dateKey);

                return $board;
            } catch (\Throwable) {
                $cached = $this->loadCached($config, $dateKey);
                if ($cached !== null) {
                    return $cached;
                }
            }
        }

        $board = $this->generateFallback($dateKey);
        $this->persistCache($context, $board, $dateKey);

        return $board;
    }

    private function generateFallback(string $dateKey): HoroscopeBoardView
    {
        $signs = [];
        foreach (HoroscopeSignRegistry::all() as $meta) {
            $content = $this->fallbackContent->forSign($meta['slug'], $dateKey);
            $signs[] = new HoroscopeSignView(
                slug: $meta['slug'],
                name: $meta['name'],
                symbol: $meta['symbol'],
                dateRange: $meta['dateRange'],
                headline: $content['headline'],
                text: $content['text'],
            );
        }

        $date = new \DateTimeImmutable($dateKey, new \DateTimeZone('Europe/Madrid'));

        return new HoroscopeBoardView(
            signs: $signs,
            date: $date,
            generatedAt: new \DateTimeImmutable(),
        );
    }

    private function generateWithAi(string $dateKey, string $apiKey, string $model): HoroscopeBoardView
    {
        $signList = [];
        foreach (HoroscopeSignRegistry::all() as $meta) {
            $signList[] = $meta['slug'].' ('.$meta['name'].')';
        }

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model !== '' ? $model : 'gpt-4o-mini',
                'temperature' => 0.65,
                'max_tokens' => 2200,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres el astrólogo de un periódico digital español. Escribe horóscopos diarios breves, positivos y prácticos para cada signo. Tono cercano, sin fatalismo. Responde SOLO con JSON: {"signs":[{"slug":"aries","headline":"titular corto","text":"2-3 frases"}]} para los 12 signos: aries, tauro, geminis, cancer, leo, virgo, libra, escorpio, sagitario, capricornio, acuario, piscis.',
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Horóscopo para el día '.$dateKey.' en España.',
                    ],
                ],
            ],
            'timeout' => 45,
        ]);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $content = $payload['choices'][0]['message']['content'] ?? '';
        if (!is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Respuesta vacía de OpenAI.');
        }

        $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $rows = $parsed['signs'] ?? [];
        if (!is_array($rows)) {
            throw new \RuntimeException('Formato de horóscopo inválido.');
        }

        $bySlug = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $slug = (string) ($row['slug'] ?? '');
            if ($slug !== '') {
                $bySlug[$slug] = $row;
            }
        }

        $signs = [];
        foreach (HoroscopeSignRegistry::all() as $meta) {
            $row = $bySlug[$meta['slug']] ?? null;
            if ($row === null) {
                $content = $this->fallbackContent->forSign($meta['slug'], $dateKey);
                $headline = $content['headline'];
                $text = $content['text'];
            } else {
                $headline = trim((string) ($row['headline'] ?? ''));
                $text = trim((string) ($row['text'] ?? ''));
                if ($headline === '' || $text === '') {
                    $content = $this->fallbackContent->forSign($meta['slug'], $dateKey);
                    $headline = $headline !== '' ? $headline : $content['headline'];
                    $text = $text !== '' ? $text : $content['text'];
                }
            }

            $signs[] = new HoroscopeSignView(
                slug: $meta['slug'],
                name: $meta['name'],
                symbol: $meta['symbol'],
                dateRange: $meta['dateRange'],
                headline: $headline,
                text: $text,
            );
        }

        $date = new \DateTimeImmutable($dateKey, new \DateTimeZone('Europe/Madrid'));

        return new HoroscopeBoardView(
            signs: $signs,
            date: $date,
            generatedAt: new \DateTimeImmutable(),
        );
    }

    private function loadCached(HoroscopeConfig $config, string $dateKey): ?HoroscopeBoardView
    {
        if ($config->dailyJson === '') {
            return null;
        }

        try {
            $data = json_decode($config->dailyJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) || ($data['dateKey'] ?? '') !== $dateKey) {
            return null;
        }

        $signsRaw = $data['signs'] ?? [];
        if (!is_array($signsRaw)) {
            return null;
        }

        $signs = [];
        foreach ($signsRaw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $signs[] = new HoroscopeSignView(
                slug: (string) ($row['slug'] ?? ''),
                name: (string) ($row['name'] ?? ''),
                symbol: (string) ($row['symbol'] ?? ''),
                dateRange: (string) ($row['dateRange'] ?? ''),
                headline: (string) ($row['headline'] ?? ''),
                text: (string) ($row['text'] ?? ''),
            );
        }

        if ($signs === []) {
            return null;
        }

        $generatedAt = isset($data['generatedAt']) && is_string($data['generatedAt'])
            ? new \DateTimeImmutable($data['generatedAt'])
            : ($config->dailyCachedAt ?? new \DateTimeImmutable());

        $date = new \DateTimeImmutable($dateKey, new \DateTimeZone('Europe/Madrid'));

        return new HoroscopeBoardView(signs: $signs, date: $date, generatedAt: $generatedAt);
    }

    private function persistCache(SiteContext $context, HoroscopeBoardView $board, string $dateKey): void
    {
        $signs = [];
        foreach ($board->signs as $sign) {
            $signs[] = [
                'slug' => $sign->slug,
                'name' => $sign->name,
                'symbol' => $sign->symbol,
                'dateRange' => $sign->dateRange,
                'headline' => $sign->headline,
                'text' => $sign->text,
            ];
        }

        $payload = json_encode([
            'dateKey' => $dateKey,
            'signs' => $signs,
            'generatedAt' => $board->generatedAt->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        $this->settingsWriter->setMany($context, [
            ['horoscope', 'daily_json', $payload],
            ['horoscope', 'daily_cached_at', $board->generatedAt->format(\DateTimeInterface::ATOM)],
            ['horoscope', 'daily_date_key', $dateKey],
        ]);
    }

    private function todayKey(): string
    {
        return (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d');
    }
}
