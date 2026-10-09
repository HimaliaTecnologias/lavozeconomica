<?php

namespace App\Core\Site\Dto;

final readonly class MarketEquitySnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public string $symbol,
        public string $ticker,
        public string $market,
        public string $currency,
        public ?float $price,
        public ?float $previousClose,
        public ?float $change,
        public ?float $changePercent,
        public ?float $dayHigh = null,
        public ?float $dayLow = null,
        public ?float $volume = null,
    ) {}

    public function isUp(): bool
    {
        return $this->change !== null && $this->change > 0;
    }

    public function isDown(): bool
    {
        return $this->change !== null && $this->change < 0;
    }
}
