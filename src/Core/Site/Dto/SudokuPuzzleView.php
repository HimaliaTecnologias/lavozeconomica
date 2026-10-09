<?php

namespace App\Core\Site\Dto;

final readonly class SudokuPuzzleView
{
    /**
     * @param list<list<int>> $puzzle
     * @param list<list<int>> $solution
     */
    public function __construct(
        public string $dateKey,
        public array $puzzle,
        public array $solution,
        public int $size = 9,
    ) {}
}
