<?php

namespace App\Core\Site\Dto;

final readonly class WeatherForecastView
{
    public function __construct(
        public string $placeName,
        public string $postalCode,
        public ?float $currentTemp,
        public ?int $currentWeatherCode,
        public string $currentDescription,
        /** @var list<array{date: string, label: string, min: ?float, max: ?float, code: int, description: string, precip: ?int}> */
        public array $daily,
        public \DateTimeImmutable $fetchedAt,
    ) {}
}
