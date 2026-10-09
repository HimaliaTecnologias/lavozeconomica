<?php

namespace App\Core\Site\Dto;

final readonly class MarketBoardView
{
    /**
     * @param list<MarketIndexSnapshot>   $indices
     * @param list<MarketEquitySnapshot>  $equities
     */
    public function __construct(
        public array $indices,
        public \DateTimeImmutable $fetchedAt,
        public array $equities = [],
    ) {}

    public function headline(): ?MarketIndexSnapshot
    {
        foreach ($this->indices as $index) {
            if ($index->id === 'ibex35') {
                return $index;
            }
        }

        return $this->indices[0] ?? null;
    }

    /**
     * @return list<MarketIndexSnapshot>
     */
    public function forRegion(string $region, bool $excludeHeadline = true): array
    {
        $items = [];
        foreach ($this->indices as $index) {
            if ($index->region !== $region) {
                continue;
            }
            if ($excludeHeadline && $index->id === 'ibex35') {
                continue;
            }
            $items[] = $index;
        }

        return $items;
    }

    /**
     * @return list<MarketEquitySnapshot>
     */
    public function equitiesForMarket(string $market): array
    {
        $items = [];
        foreach ($this->equities as $equity) {
            if ($equity->market === $market) {
                $items[] = $equity;
            }
        }

        usort($items, static function (MarketEquitySnapshot $a, MarketEquitySnapshot $b): int {
            $ap = $a->changePercent ?? -9999.0;
            $bp = $b->changePercent ?? -9999.0;

            return $bp <=> $ap;
        });

        return $items;
    }

    /**
     * @return array{up: int, down: int, flat: int}
     */
    public function sentiment(): array
    {
        $up = 0;
        $down = 0;
        $flat = 0;
        foreach ($this->indices as $index) {
            if ($index->isUp()) {
                ++$up;
            } elseif ($index->isDown()) {
                ++$down;
            } else {
                ++$flat;
            }
        }

        return ['up' => $up, 'down' => $down, 'flat' => $flat];
    }

    /**
     * @return array{up: int, down: int, flat: int}
     */
    public function equitySentiment(string $market = 'ibex35'): array
    {
        $up = 0;
        $down = 0;
        $flat = 0;
        foreach ($this->equitiesForMarket($market) as $equity) {
            if ($equity->isUp()) {
                ++$up;
            } elseif ($equity->isDown()) {
                ++$down;
            } else {
                ++$flat;
            }
        }

        return ['up' => $up, 'down' => $down, 'flat' => $flat];
    }
}
