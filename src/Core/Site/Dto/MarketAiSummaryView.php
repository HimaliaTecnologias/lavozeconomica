<?php

namespace App\Core\Site\Dto;

final readonly class MarketAiSummaryView
{
    /** @param list<string> $paragraphs */
    public function __construct(
        public string $headline,
        public array $paragraphs,
        public \DateTimeImmutable $generatedAt,
    ) {}
}
