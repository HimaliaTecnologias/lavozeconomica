<?php

namespace App\Markets;

use App\Core\Site\Dto\MarketBoardView;
use App\Core\Site\Dto\MarketConfig;
use App\Core\Site\Dto\MarketEquitySnapshot;
use App\Core\Site\Dto\MarketIndexSnapshot;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MarketService
{
    private const CACHE_TTL_SECONDS = 300;

    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function getConfig(SiteContext $context): MarketConfig
    {
        return $this->settingsReader->getMarketConfig($context);
    }

    public function getBoard(SiteContext $context, bool $forceRefresh = false): ?MarketBoardView
    {
        $config = $this->getConfig($context);
        if (!$config->enabled || !$config->isConfigured()) {
            return null;
        }

        if (!$forceRefresh && $this->isCacheFresh($config)) {
            $cached = $this->loadCachedBoard($config);
            if ($cached !== null && !$this->boardNeedsRefresh($cached)) {
                return $cached;
            }
        }

        try {
            $board = $this->fetchBoard($config);
            $this->persistCache($context, $board);

            return $board;
        } catch (\Throwable) {
            return $this->loadCachedBoard($config);
        }
    }

    private function fetchBoard(MarketConfig $config): MarketBoardView
    {
        $snapshots = [];
        foreach ($config->indexIds as $indexId) {
            $definition = MarketIndexRegistry::find($indexId);
            if ($definition === null) {
                continue;
            }

            try {
                $snapshots[] = $this->fetchIndexSnapshot($definition);
            } catch (\Throwable) {
                // Continuar con el resto de índices si uno falla.
            }
        }

        if ($snapshots === []) {
            throw new \RuntimeException('No se pudo obtener ningún índice de mercado.');
        }

        $equities = $this->fetchEquities();

        return new MarketBoardView(
            indices: $snapshots,
            fetchedAt: new \DateTimeImmutable(),
            equities: $equities,
        );
    }

    /**
     * @return list<MarketEquitySnapshot>
     */
    private function fetchEquities(): array
    {
        $definitions = MarketEquityRegistry::all();
        $bySymbol = [];
        foreach ($definitions as $definition) {
            $bySymbol[$definition['symbol']] = $definition;
        }

        $symbols = array_keys($bySymbol);
        $quotes = $this->fetchYahooQuotes($symbols);

        $equities = [];
        foreach ($definitions as $definition) {
            $quote = $quotes[$definition['symbol']] ?? null;
            if ($quote === null) {
                continue;
            }
            $equities[] = $quote;
        }

        return $equities;
    }

    /**
     * @param list<string> $symbols
     *
     * @return array<string, MarketEquitySnapshot>
     */
    private function fetchYahooQuotes(array $symbols): array
    {
        if ($symbols === []) {
            return [];
        }

        return $this->fetchYahooQuotesViaChart($symbols);
    }

    /**
     * @param list<string> $symbols
     *
     * @return array<string, MarketEquitySnapshot>
     */
    private function fetchYahooQuotesViaChart(array $symbols): array
    {
        $definitions = [];
        foreach (MarketEquityRegistry::all() as $definition) {
            $definitions[$definition['symbol']] = $definition;
        }

        $responses = [];
        foreach ($symbols as $symbol) {
            $url = sprintf(
                'https://query1.finance.yahoo.com/v8/finance/chart/%s?interval=1d&range=2d',
                rawurlencode($symbol),
            );
            $responses[$symbol] = $this->httpClient->request('GET', $url, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; MyMediaCMS/1.0)',
                ],
            ]);
        }

        $out = [];
        foreach ($responses as $symbol => $response) {
            try {
                $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $meta = $data['chart']['result'][0]['meta'] ?? null;
                if (!is_array($meta)) {
                    continue;
                }
                $definition = $definitions[$symbol] ?? null;
                if ($definition === null) {
                    continue;
                }

                $price = isset($meta['regularMarketPrice']) ? (float) $meta['regularMarketPrice'] : null;
                $previousClose = isset($meta['chartPreviousClose']) ? (float) $meta['chartPreviousClose'] : null;
                if ($previousClose === null && isset($meta['previousClose'])) {
                    $previousClose = (float) $meta['previousClose'];
                }

                $change = null;
                $changePercent = null;
                if ($price !== null && $previousClose !== null && $previousClose !== 0.0) {
                    $change = round($price - $previousClose, 4);
                    $changePercent = round(($change / $previousClose) * 100, 2);
                }

                $out[$symbol] = new MarketEquitySnapshot(
                    id: $definition['id'],
                    name: $definition['name'],
                    symbol: $definition['symbol'],
                    ticker: $definition['ticker'],
                    market: $definition['market'],
                    currency: (string) ($meta['currency'] ?? 'EUR'),
                    price: $price,
                    previousClose: $previousClose,
                    change: $change,
                    changePercent: $changePercent,
                    dayHigh: isset($meta['regularMarketDayHigh']) ? (float) $meta['regularMarketDayHigh'] : null,
                    dayLow: isset($meta['regularMarketDayLow']) ? (float) $meta['regularMarketDayLow'] : null,
                    volume: isset($meta['regularMarketVolume']) ? (float) $meta['regularMarketVolume'] : null,
                );
            } catch (\Throwable) {
                continue;
            }
        }

        return $out;
    }

    /**
     * @param array{id: string, name: string, symbol: string, description: string, currency: string, region: string} $definition
     */
    private function fetchIndexSnapshot(array $definition): MarketIndexSnapshot
    {
        $url = sprintf(
            'https://query1.finance.yahoo.com/v8/finance/chart/%s?interval=1d&range=5d',
            rawurlencode($definition['symbol']),
        );

        $response = $this->httpClient->request('GET', $url, [
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; MyMediaCMS/1.0)',
            ],
        ]);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $result = $data['chart']['result'][0] ?? null;
        if (!is_array($result)) {
            throw new \RuntimeException('Respuesta de mercado inválida para '.$definition['symbol']);
        }

        $meta = $result['meta'] ?? [];
        $quote = $result['indicators']['quote'][0] ?? [];
        $closes = $quote['close'] ?? [];
        $sparkline = [];
        if (is_array($closes)) {
            foreach ($closes as $close) {
                if ($close !== null && is_numeric($close)) {
                    $sparkline[] = round((float) $close, 4);
                }
            }
        }

        $price = isset($meta['regularMarketPrice']) ? (float) $meta['regularMarketPrice'] : null;
        $previousClose = isset($meta['chartPreviousClose']) ? (float) $meta['chartPreviousClose'] : null;
        if ($price === null && isset($meta['previousClose'])) {
            $price = (float) $meta['previousClose'];
        }

        $dayHigh = isset($meta['regularMarketDayHigh']) ? (float) $meta['regularMarketDayHigh'] : null;
        $dayLow = isset($meta['regularMarketDayLow']) ? (float) $meta['regularMarketDayLow'] : null;

        $change = null;
        $changePercent = null;
        if ($price !== null && $previousClose !== null && $previousClose !== 0.0) {
            $change = round($price - $previousClose, 4);
            $changePercent = round(($change / $previousClose) * 100, 2);
        }

        $marketTime = null;
        if (isset($meta['regularMarketTime']) && is_numeric($meta['regularMarketTime'])) {
            $marketTime = \DateTimeImmutable::createFromFormat('U', (string) (int) $meta['regularMarketTime']) ?: null;
        }

        return new MarketIndexSnapshot(
            id: $definition['id'],
            name: $definition['name'],
            symbol: $definition['symbol'],
            currency: (string) ($meta['currency'] ?? $definition['currency']),
            region: $definition['region'],
            description: $definition['description'],
            price: $price,
            previousClose: $previousClose,
            change: $change,
            changePercent: $changePercent,
            dayHigh: $dayHigh,
            dayLow: $dayLow,
            marketTime: $marketTime,
            exchangeName: (string) ($meta['fullExchangeName'] ?? $meta['exchangeName'] ?? ''),
            sparkline: $sparkline,
        );
    }

    private function isCacheFresh(MarketConfig $config): bool
    {
        if ($config->cachedAt === null) {
            return false;
        }

        return $config->cachedAt->getTimestamp() + self::CACHE_TTL_SECONDS > time();
    }

    private function boardNeedsRefresh(MarketBoardView $board): bool
    {
        if ($board->indices === []) {
            return true;
        }

        if ($board->equities === []) {
            return true;
        }

        foreach ($board->indices as $index) {
            if (count($index->sparkline) >= 2) {
                return false;
            }
        }

        return true;
    }

    private function loadCachedBoard(MarketConfig $config): ?MarketBoardView
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

        $indicesRaw = $data['indices'] ?? [];
        if (!is_array($indicesRaw)) {
            return null;
        }

        $indices = [];
        foreach ($indicesRaw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $marketTime = null;
            if (isset($row['marketTime']) && is_string($row['marketTime']) && $row['marketTime'] !== '') {
                try {
                    $marketTime = new \DateTimeImmutable($row['marketTime']);
                } catch (\Throwable) {
                    $marketTime = null;
                }
            }

            $indices[] = new MarketIndexSnapshot(
                id: (string) ($row['id'] ?? ''),
                name: (string) ($row['name'] ?? ''),
                symbol: (string) ($row['symbol'] ?? ''),
                currency: (string) ($row['currency'] ?? 'EUR'),
                region: (string) ($row['region'] ?? 'spain'),
                description: (string) ($row['description'] ?? ''),
                price: isset($row['price']) ? (float) $row['price'] : null,
                previousClose: isset($row['previousClose']) ? (float) $row['previousClose'] : null,
                change: isset($row['change']) ? (float) $row['change'] : null,
                changePercent: isset($row['changePercent']) ? (float) $row['changePercent'] : null,
                dayHigh: isset($row['dayHigh']) ? (float) $row['dayHigh'] : null,
                dayLow: isset($row['dayLow']) ? (float) $row['dayLow'] : null,
                marketTime: $marketTime,
                exchangeName: (string) ($row['exchangeName'] ?? ''),
                sparkline: is_array($row['sparkline'] ?? null) ? array_map('floatval', $row['sparkline']) : [],
            );
        }

        $equities = [];
        $equitiesRaw = $data['equities'] ?? [];
        if (is_array($equitiesRaw)) {
            foreach ($equitiesRaw as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $equities[] = new MarketEquitySnapshot(
                    id: (string) ($row['id'] ?? ''),
                    name: (string) ($row['name'] ?? ''),
                    symbol: (string) ($row['symbol'] ?? ''),
                    ticker: (string) ($row['ticker'] ?? ''),
                    market: (string) ($row['market'] ?? 'ibex35'),
                    currency: (string) ($row['currency'] ?? 'EUR'),
                    price: isset($row['price']) ? (float) $row['price'] : null,
                    previousClose: isset($row['previousClose']) ? (float) $row['previousClose'] : null,
                    change: isset($row['change']) ? (float) $row['change'] : null,
                    changePercent: isset($row['changePercent']) ? (float) $row['changePercent'] : null,
                    dayHigh: isset($row['dayHigh']) ? (float) $row['dayHigh'] : null,
                    dayLow: isset($row['dayLow']) ? (float) $row['dayLow'] : null,
                    volume: isset($row['volume']) ? (float) $row['volume'] : null,
                );
            }
        }

        $fetchedAt = isset($data['fetchedAt']) && is_string($data['fetchedAt'])
            ? new \DateTimeImmutable($data['fetchedAt'])
            : ($config->cachedAt ?? new \DateTimeImmutable());

        return new MarketBoardView(indices: $indices, fetchedAt: $fetchedAt, equities: $equities);
    }

    private function persistCache(SiteContext $context, MarketBoardView $board): void
    {
        $indices = [];
        foreach ($board->indices as $snapshot) {
            $indices[] = [
                'id' => $snapshot->id,
                'name' => $snapshot->name,
                'symbol' => $snapshot->symbol,
                'currency' => $snapshot->currency,
                'region' => $snapshot->region,
                'description' => $snapshot->description,
                'price' => $snapshot->price,
                'previousClose' => $snapshot->previousClose,
                'change' => $snapshot->change,
                'changePercent' => $snapshot->changePercent,
                'dayHigh' => $snapshot->dayHigh,
                'dayLow' => $snapshot->dayLow,
                'marketTime' => $snapshot->marketTime?->format(\DateTimeInterface::ATOM),
                'exchangeName' => $snapshot->exchangeName,
                'sparkline' => $snapshot->sparkline,
            ];
        }

        $equities = [];
        foreach ($board->equities as $equity) {
            $equities[] = [
                'id' => $equity->id,
                'name' => $equity->name,
                'symbol' => $equity->symbol,
                'ticker' => $equity->ticker,
                'market' => $equity->market,
                'currency' => $equity->currency,
                'price' => $equity->price,
                'previousClose' => $equity->previousClose,
                'change' => $equity->change,
                'changePercent' => $equity->changePercent,
                'dayHigh' => $equity->dayHigh,
                'dayLow' => $equity->dayLow,
                'volume' => $equity->volume,
            ];
        }

        $payload = json_encode([
            'indices' => $indices,
            'equities' => $equities,
            'fetchedAt' => $board->fetchedAt->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        $this->settingsWriter->setMany($context, [
            ['markets', 'cached_json', $payload],
            ['markets', 'cached_at', $board->fetchedAt->format(\DateTimeInterface::ATOM)],
        ]);
    }
}
