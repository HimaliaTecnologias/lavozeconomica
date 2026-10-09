<?php

namespace App\Games;

use App\Core\Site\Dto\GamesConfig;
use App\Core\Site\Dto\SudokuPuzzleView;
use App\Core\Site\Dto\WordScramblePuzzleView;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;

final class GamesService
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly SudokuGenerator $sudokuGenerator,
        private readonly WordScrambleGenerator $wordScrambleGenerator,
    ) {}

    public function getConfig(SiteContext $context): GamesConfig
    {
        return $this->settingsReader->getGamesConfig($context);
    }

    public function getDailySudoku(SiteContext $context, bool $forceRefresh = false): ?SudokuPuzzleView
    {
        $config = $this->getConfig($context);
        if (!$config->enabled || !$config->sudokuEnabled) {
            return null;
        }

        $dateKey = $this->todayKey();
        if (!$forceRefresh && $config->sudokuCachedDate === $dateKey && $config->sudokuCachedJson !== '') {
            $cached = $this->loadSudoku($config->sudokuCachedJson, $dateKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $seed = $context->code.'-'.$dateKey;
        $generated = $this->sudokuGenerator->generateDaily($seed, 36);
        $puzzle = new SudokuPuzzleView(
            dateKey: $dateKey,
            puzzle: $generated['puzzle'],
            solution: $generated['solution'],
        );
        $this->persistSudoku($context, $puzzle);

        return $puzzle;
    }

    public function getDailyWordScramble(SiteContext $context, bool $forceRefresh = false): ?WordScramblePuzzleView
    {
        $config = $this->getConfig($context);
        if (!$config->enabled || !$config->wordScrambleEnabled) {
            return null;
        }

        $dateKey = $this->todayKey();
        if (!$forceRefresh && $config->wordScrambleCachedDate === $dateKey && $config->wordScrambleCachedJson !== '') {
            $cached = $this->loadWordScramble($config->wordScrambleCachedJson, $dateKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $seed = $context->code.'-'.$dateKey;
        $generated = $this->wordScrambleGenerator->generateDaily($seed);
        $puzzle = new WordScramblePuzzleView(
            dateKey: $dateKey,
            word: $generated['word'],
            scrambled: $generated['scrambled'],
            hint: $generated['hint'],
        );
        $this->persistWordScramble($context, $puzzle);

        return $puzzle;
    }

    private function todayKey(): string
    {
        return (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d');
    }

    private function loadSudoku(string $json, string $dateKey): ?SudokuPuzzleView
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) || ($data['dateKey'] ?? '') !== $dateKey) {
            return null;
        }

        $puzzle = $data['puzzle'] ?? null;
        $solution = $data['solution'] ?? null;
        if (!is_array($puzzle) || !is_array($solution)) {
            return null;
        }

        return new SudokuPuzzleView(
            dateKey: $dateKey,
            puzzle: array_map(static fn (array $row): array => array_map('intval', $row), $puzzle),
            solution: array_map(static fn (array $row): array => array_map('intval', $row), $solution),
        );
    }

    private function loadWordScramble(string $json, string $dateKey): ?WordScramblePuzzleView
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data) || ($data['dateKey'] ?? '') !== $dateKey) {
            return null;
        }

        return new WordScramblePuzzleView(
            dateKey: $dateKey,
            word: (string) ($data['word'] ?? ''),
            scrambled: (string) ($data['scrambled'] ?? ''),
            hint: (string) ($data['hint'] ?? ''),
        );
    }

    private function persistSudoku(SiteContext $context, SudokuPuzzleView $puzzle): void
    {
        $payload = json_encode([
            'dateKey' => $puzzle->dateKey,
            'puzzle' => $puzzle->puzzle,
            'solution' => $puzzle->solution,
        ], JSON_THROW_ON_ERROR);

        $this->settingsWriter->setMany($context, [
            ['games', 'sudoku_cached_json', $payload],
            ['games', 'sudoku_cached_date', $puzzle->dateKey],
        ]);
    }

    private function persistWordScramble(SiteContext $context, WordScramblePuzzleView $puzzle): void
    {
        $payload = json_encode([
            'dateKey' => $puzzle->dateKey,
            'word' => $puzzle->word,
            'scrambled' => $puzzle->scrambled,
            'hint' => $puzzle->hint,
        ], JSON_THROW_ON_ERROR);

        $this->settingsWriter->setMany($context, [
            ['games', 'word_scramble_cached_json', $payload],
            ['games', 'word_scramble_cached_date', $puzzle->dateKey],
        ]);
    }
}
