<?php

namespace App\Core\Site\Dto;

final readonly class MarketIndexSnapshot
{
    /**
     * @param list<float> $sparkline
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $symbol,
        public string $currency,
        public string $region,
        public string $description,
        public ?float $price,
        public ?float $previousClose,
        public ?float $change,
        public ?float $changePercent,
        public ?float $dayHigh = null,
        public ?float $dayLow = null,
        public ?\DateTimeImmutable $marketTime = null,
        public string $exchangeName = '',
        public array $sparkline = [],
    ) {}

    public function isUp(): bool
    {
        return $this->change !== null && $this->change > 0;
    }

    public function isDown(): bool
    {
        return $this->change !== null && $this->change < 0;
    }

    public function isFlat(): bool
    {
        return $this->change === null || abs($this->change) < 0.0001;
    }
}
