<?php

namespace App\Games;

final class SudokuGenerator
{
    /**
     * @return array{puzzle: list<list<int>>, solution: list<list<int>>}
     */
    public function generateDaily(string $seed, int $clues = 36): array
    {
        $rng = $this->seededRng($seed.'-sudoku');
        $solution = $this->createSolution($rng);
        $puzzle = $this->carvePuzzle($solution, $clues, $rng);

        return ['puzzle' => $puzzle, 'solution' => $solution];
    }

    /** @return list<list<int>> */
    private function createSolution(int $seed): array
    {
        $grid = array_fill(0, 9, array_fill(0, 9, 0));
        $this->fillCell($grid, 0, 0, $seed);

        return $grid;
    }

    /** @param list<list<int>> $grid */
    private function fillCell(array &$grid, int $row, int $col, int $seed): bool
    {
        if ($row === 9) {
            return true;
        }

        $nextRow = $col === 8 ? $row + 1 : $row;
        $nextCol = $col === 8 ? 0 : $col + 1;

        if ($grid[$row][$col] !== 0) {
            return $this->fillCell($grid, $nextRow, $nextCol, $seed);
        }

        $numbers = range(1, 9);
        $this->shuffleWithSeed($numbers, $seed + $row * 9 + $col);

        foreach ($numbers as $number) {
            if ($this->isValidPlacement($grid, $row, $col, $number)) {
                $grid[$row][$col] = $number;
                if ($this->fillCell($grid, $nextRow, $nextCol, $seed + $number)) {
                    return true;
                }
                $grid[$row][$col] = 0;
            }
        }

        return false;
    }

    /** @param list<list<int>> $grid */
    private function isValidPlacement(array $grid, int $row, int $col, int $number): bool
    {
        for ($c = 0; $c < 9; ++$c) {
            if ($grid[$row][$c] === $number) {
                return false;
            }
        }
        for ($r = 0; $r < 9; ++$r) {
            if ($grid[$r][$col] === $number) {
                return false;
            }
        }
        $boxRow = intdiv($row, 3) * 3;
        $boxCol = intdiv($col, 3) * 3;
        for ($r = $boxRow; $r < $boxRow + 3; ++$r) {
            for ($c = $boxCol; $c < $boxCol + 3; ++$c) {
                if ($grid[$r][$c] === $number) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param list<list<int>> $solution
     *
     * @return list<list<int>>
     */
    private function carvePuzzle(array $solution, int $clues, int $seed): array
    {
        $puzzle = $solution;
        $cells = [];
        for ($r = 0; $r < 9; ++$r) {
            for ($c = 0; $c < 9; ++$c) {
                $cells[] = [$r, $c];
            }
        }
        $this->shuffleWithSeed($cells, $seed);

        $toRemove = 81 - max(22, min(60, $clues));
        for ($i = 0; $i < $toRemove && $i < count($cells); ++$i) {
            [$r, $c] = $cells[$i];
            $puzzle[$r][$c] = 0;
        }

        return $puzzle;
    }

    /** @param list<mixed> $items */
    private function shuffleWithSeed(array &$items, int $seed): void
    {
        $count = count($items);
        for ($i = $count - 1; $i > 0; --$i) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $j = $seed % ($i + 1);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }

    private function seededRng(string $seed): int
    {
        return crc32($seed) & 0x7fffffff;
    }
}
