<?php

namespace App\Markets;

use App\Ai\OpenaiApiKeyResolver;
use App\Core\Site\Dto\MarketAiSummaryView;
use App\Core\Site\Dto\MarketBoardView;
use App\Core\Site\Dto\MarketConfig;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MarketAiSummaryService
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly HttpClientInterface $httpClient,
        private readonly OpenaiApiKeyResolver $apiKeyResolver,
    ) {}

    public function getSummary(SiteContext $context, MarketBoardView $board, bool $forceRefresh = false): ?MarketAiSummaryView
    {
        $config = $this->settingsReader->getMarketConfig($context);
        if (!$config->aiSummaryEnabled) {
            return null;
        }

        $apiKey = $this->apiKeyResolver->resolve($context, $config->openaiApiKey);
        if ($apiKey === '') {
            return null;
        }

        if (!$forceRefresh && $this->isCacheValid($config, $board)) {
            return $this->loadCached($config);
        }

        try {
            $model = $this->settingsReader->getAiConfig($context)->model;
            $summary = $this->generate($board, $apiKey, $model);
            $this->persistCache($context, $board, $summary);

            return $summary;
        } catch (\Throwable) {
            return $this->loadCached($config);
        }
    }

    private function isCacheValid(MarketConfig $config, MarketBoardView $board): bool
    {
        if ($config->aiSummaryJson === '' || $config->aiSummaryCachedAt === null) {
            return false;
        }

        if ($config->aiSummaryBoardAt === null) {
            return false;
        }

        return $config->aiSummaryBoardAt->getTimestamp() === $board->fetchedAt->getTimestamp();
    }

    private function loadCached(MarketConfig $config): ?MarketAiSummaryView
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

        return new MarketAiSummaryView(
            headline: $headline,
            paragraphs: array_values(array_filter(array_map('strval', $paragraphs))),
            generatedAt: $generatedAt,
        );
    }

    private function generate(MarketBoardView $board, string $apiKey, string $model): MarketAiSummaryView
    {
        $sentiment = $board->sentiment();
        $lines = [];
        foreach ($board->indices as $index) {
            $trend = MarketFormatter::sparklineTrendLabel($index->sparkline);
            $line = sprintf(
                '%s (%s, %s): último %s, variación %s (%s), cierre ant. %s',
                $index->name,
                $index->symbol,
                MarketFormatter::regionLabel($index->region),
                MarketFormatter::formatPrice($index->price, $index->currency),
                MarketFormatter::formatChange($index->change, $index->currency),
                MarketFormatter::formatChangePercent($index->changePercent),
                MarketFormatter::formatPrice($index->previousClose, $index->currency),
            );
            if ($index->dayLow !== null || $index->dayHigh !== null) {
                $line .= sprintf(
                    ', sesión %s – %s',
                    MarketFormatter::formatPrice($index->dayLow, $index->currency),
                    MarketFormatter::formatPrice($index->dayHigh, $index->currency),
                );
            }
            if ($trend !== '') {
                $line .= ', tendencia 5 días: '.$trend;
            }
            if ($index->exchangeName !== '') {
                $line .= ', mercado '.$index->exchangeName;
            }
            $lines[] = $line;
        }

        $userPrompt = sprintf(
            "Cotizaciones actualizadas: %s\nResumen: %d índices en alza, %d en baja, %d planos\n\nDetalle por índice:\n%s\n\nGenera un comentario de mercados para lectores de un periódico digital español.",
            $board->fetchedAt->format('d/m/Y H:i'),
            $sentiment['up'],
            $sentiment['down'],
            $sentiment['flat'],
            implode("\n", $lines),
        );

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model !== '' ? $model : 'gpt-4o-mini',
                'temperature' => 0.5,
                'max_tokens' => 1600,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres el analista de mercados de un periódico digital español. Escribe un comentario editorial sobre la sesión y el contexto de los índices: panorama general (España, Europa, EE. UU.), qué destaca en el IBEX y otros mercados, correlaciones o divergencias visibles en los datos, y qué vigilar en la próxima sesión. No inventes causas (guerras, resultados empresariales, etc.) que no estén en los datos. No es consejo de inversión. Tono claro, profesional y accesible. Responde SOLO con JSON válido: {"headline":"titular evocador","paragraphs":["párrafo 1","párrafo 2",...]}. Entre 4 y 5 párrafos desarrollados (3-4 frases cada uno).',
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
            throw new \RuntimeException('Formato de comentario IA inválido.');
        }

        $cleanParagraphs = [];
        foreach ($paragraphs as $paragraph) {
            $text = trim((string) $paragraph);
            if ($text !== '') {
                $cleanParagraphs[] = $text;
            }
        }

        if ($cleanParagraphs === []) {
            throw new \RuntimeException('El comentario IA no contiene párrafos.');
        }

        return new MarketAiSummaryView(
            headline: $headline,
            paragraphs: $cleanParagraphs,
            generatedAt: new \DateTimeImmutable(),
        );
    }

    private function persistCache(SiteContext $context, MarketBoardView $board, MarketAiSummaryView $summary): void
    {
        $payload = json_encode([
            'headline' => $summary->headline,
            'paragraphs' => $summary->paragraphs,
            'generatedAt' => $summary->generatedAt->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        $this->settingsWriter->setMany($context, [
            ['markets', 'ai_summary_json', $payload],
            ['markets', 'ai_summary_cached_at', $summary->generatedAt->format(\DateTimeInterface::ATOM)],
            ['markets', 'ai_summary_board_at', $board->fetchedAt->format(\DateTimeInterface::ATOM)],
        ]);
    }
}
