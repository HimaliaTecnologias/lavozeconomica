<?php

namespace App\Weather;

use App\Ai\OpenaiApiKeyResolver;
use App\Core\Site\Dto\WeatherAiSummaryView;
use App\Core\Site\Dto\WeatherConfig;
use App\Core\Site\Dto\WeatherForecastView;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeatherAiSummaryService
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly HttpClientInterface $httpClient,
        private readonly OpenaiApiKeyResolver $apiKeyResolver,
    ) {}

    public function getSummary(SiteContext $context, WeatherForecastView $forecast, bool $forceRefresh = false): ?WeatherAiSummaryView
    {
        $config = $this->settingsReader->getWeatherConfig($context);
        if (!$config->aiSummaryEnabled) {
            return null;
        }

        $apiKey = $this->apiKeyResolver->resolve($context, $config->openaiApiKey);
        if ($apiKey === '') {
            return null;
        }

        if (!$forceRefresh && $this->isCacheValid($config, $forecast)) {
            return $this->loadCached($config);
        }

        try {
            $model = $this->settingsReader->getAiConfig($context)->model;
            $summary = $this->generate($forecast, $apiKey, $model);
            $this->persistCache($context, $forecast, $summary);

            return $summary;
        } catch (\Throwable) {
            return $this->loadCached($config);
        }
    }

    private function isCacheValid(WeatherConfig $config, WeatherForecastView $forecast): bool
    {
        if ($config->aiSummaryJson === '' || $config->aiSummaryCachedAt === null) {
            return false;
        }

        if ($config->aiSummaryForecastAt === null) {
            return false;
        }

        return $config->aiSummaryForecastAt->getTimestamp() === $forecast->fetchedAt->getTimestamp();
    }

    private function loadCached(WeatherConfig $config): ?WeatherAiSummaryView
    {
        if ($config->aiSummaryJson === '') {
            return null;
        }

        try {
            $data = json_decode($config->aiSummaryJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $paragraphs = $data['paragraphs'] ?? [];
        if (!is_array($paragraphs)) {
            $paragraphs = [];
        }

        $headline = trim((string) ($data['headline'] ?? ''));
        if ($headline === '' || $paragraphs === []) {
            return null;
        }

        $generatedAt = isset($data['generatedAt']) && is_string($data['generatedAt'])
            ? new \DateTimeImmutable($data['generatedAt'])
            : ($config->aiSummaryCachedAt ?? new \DateTimeImmutable());

        return new WeatherAiSummaryView(
            headline: $headline,
            paragraphs: array_values(array_filter(array_map('strval', $paragraphs))),
            generatedAt: $generatedAt,
        );
    }

    private function generate(WeatherForecastView $forecast, string $apiKey, string $model): WeatherAiSummaryView
    {
        $daysText = [];
        foreach ($forecast->daily as $day) {
            $line = sprintf(
                '%s (%s): %s, mín %s°C, máx %s°C',
                $day['label'],
                $day['date'],
                $day['description'],
                $day['min'] !== null ? (string) $day['min'] : '—',
                $day['max'] !== null ? (string) $day['max'] : '—',
            );
            if ($day['precip'] !== null && $day['precip'] > 0) {
                $line .= ', probabilidad de lluvia '.$day['precip'].'%';
            }
            $daysText[] = $line;
        }

        $userPrompt = sprintf(
            "Localidad: %s (CP %s)\nSituación actual: %s, %s°C\n\nPredicción diaria:\n%s\n\nGenera un pronóstico editorial para los próximos días en español.",
            $forecast->placeName,
            $forecast->postalCode,
            $forecast->currentDescription,
            $forecast->currentTemp !== null ? (string) $forecast->currentTemp : 'sin dato',
            implode("\n", $daysText),
        );

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model !== '' ? $model : 'gpt-4o-mini',
                'temperature' => 0.55,
                'max_tokens' => 1400,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres el meteorólogo de un periódico digital español. Escribe un pronóstico editorial amplio y detallado para los lectores: tendencia general, evolución día a día, cambios de temperatura, lluvia o viento cuando los datos lo indiquen, y consejos prácticos (ropa, actividades al aire libre, etc.) sin inventar datos que no estén en la predicción. Tono claro, cercano y profesional. Responde SOLO con JSON válido: {"headline":"titular evocador","paragraphs":["párrafo 1","párrafo 2",...]}. Entre 4 y 6 párrafos desarrollados (3-5 frases cada uno).',
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
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
        $headline = trim((string) ($parsed['headline'] ?? ''));
        $paragraphs = $parsed['paragraphs'] ?? [];
        if (!is_array($paragraphs) || $headline === '') {
            throw new \RuntimeException('Formato de resumen IA inválido.');
        }

        $cleanParagraphs = [];
        foreach ($paragraphs as $paragraph) {
            $text = trim((string) $paragraph);
            if ($text !== '') {
                $cleanParagraphs[] = $text;
            }
        }

        if ($cleanParagraphs === []) {
            throw new \RuntimeException('El resumen IA no contiene párrafos.');
        }

        return new WeatherAiSummaryView(
            headline: $headline,
            paragraphs: $cleanParagraphs,
            generatedAt: new \DateTimeImmutable(),
        );
    }

    private function persistCache(SiteContext $context, WeatherForecastView $forecast, WeatherAiSummaryView $summary): void
    {
        $payload = json_encode([
            'headline' => $summary->headline,
            'paragraphs' => $summary->paragraphs,
            'generatedAt' => $summary->generatedAt->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        $this->settingsWriter->setMany($context, [
            ['weather', 'ai_summary_json', $payload],
            ['weather', 'ai_summary_cached_at', $summary->generatedAt->format(\DateTimeInterface::ATOM)],
            ['weather', 'ai_summary_forecast_at', $forecast->fetchedAt->format(\DateTimeInterface::ATOM)],
        ]);
    }
}
