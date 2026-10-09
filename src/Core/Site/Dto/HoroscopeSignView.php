<?php

namespace App\Core\Site\Dto;

final readonly class HoroscopeSignView
{
    public function __construct(
        public string $slug,
        public string $name,
        public string $symbol,
        public string $dateRange,
        public string $headline,
        public string $text,
    ) {}
}
