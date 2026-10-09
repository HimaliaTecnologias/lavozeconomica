<?php

namespace App\Core\Site\Dto;

final readonly class WordScramblePuzzleView
{
    public function __construct(
        public string $dateKey,
        public string $word,
        public string $scrambled,
        public string $hint,
    ) {}
}
