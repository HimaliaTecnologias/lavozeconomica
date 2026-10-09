<?php

namespace App\Markets;

final class MarketIndexRegistry
{
    /** @var list<string> */
    public const DEFAULT_INDEX_IDS = [
        'ibex35',
        'mercado_continuo',
        'ibex_small',
        'eurostoxx',
        'dax',
        'cac40',
        'ftse100',
        'ftsemib',
        'aex',
        'nasdaq',
        'dow',
        'sp500',
        'sp100',
        'sp400',
        'sp600',
    ];

    /**
     * @return list<array{id: string, name: string, symbol: string, description: string, currency: string, region: string}>
     */
    public static function all(): array
    {
        return [
            [
                'id' => 'ibex35',
                'name' => 'IBEX 35',
                'symbol' => '^IBEX',
                'description' => 'Principal índice de la Bolsa española.',
                'currency' => 'EUR',
                'region' => 'spain',
            ],
            [
                'id' => 'mercado_continuo',
                'name' => 'IBEX Medium Cap',
                'symbol' => 'INDC.MC',
                'description' => 'Referencia del Mercado Continuo (mediana capitalización).',
                'currency' => 'EUR',
                'region' => 'spain',
            ],
            [
                'id' => 'ibex_small',
                'name' => 'IBEX Small Cap',
                'symbol' => 'INDS.MC',
                'description' => 'Pequeña capitalización del Mercado Continuo.',
                'currency' => 'EUR',
                'region' => 'spain',
            ],
            [
                'id' => 'eurostoxx',
                'name' => 'Euro Stoxx 50',
                'symbol' => '^STOXX50E',
                'description' => 'Las 50 mayores empresas de la zona euro.',
                'currency' => 'EUR',
                'region' => 'europe',
            ],
            [
                'id' => 'dax',
                'name' => 'DAX',
                'symbol' => '^GDAXI',
                'description' => 'Índice alemán (Fráncfort).',
                'currency' => 'EUR',
                'region' => 'europe',
            ],
            [
                'id' => 'cac40',
                'name' => 'CAC 40',
                'symbol' => '^FCHI',
                'description' => 'Índice francés (París).',
                'currency' => 'EUR',
                'region' => 'europe',
            ],
            [
                'id' => 'ftse100',
                'name' => 'FTSE 100',
                'symbol' => '^FTSE',
                'description' => 'Índice británico (Londres).',
                'currency' => 'GBP',
                'region' => 'europe',
            ],
            [
                'id' => 'ftsemib',
                'name' => 'FTSE MIB',
                'symbol' => 'FTSEMIB.MI',
                'description' => 'Índice italiano (Milán).',
                'currency' => 'EUR',
                'region' => 'europe',
            ],
            [
                'id' => 'aex',
                'name' => 'AEX',
                'symbol' => '^AEX',
                'description' => 'Índice neerlandés (Ámsterdam).',
                'currency' => 'EUR',
                'region' => 'europe',
            ],
            [
                'id' => 'smi',
                'name' => 'SMI',
                'symbol' => '^SSMI',
                'description' => 'Índice suizo (Zúrich).',
                'currency' => 'CHF',
                'region' => 'europe',
            ],
            [
                'id' => 'bel20',
                'name' => 'BEL 20',
                'symbol' => '^BFX',
                'description' => 'Índice belga (Bruselas).',
                'currency' => 'EUR',
                'region' => 'europe',
            ],
            [
                'id' => 'psi20',
                'name' => 'PSI 20',
                'symbol' => 'PSI20.LS',
                'description' => 'Índice portugués (Lisboa).',
                'currency' => 'EUR',
                'region' => 'europe',
            ],
            [
                'id' => 'nasdaq',
                'name' => 'Nasdaq',
                'symbol' => '^IXIC',
                'description' => 'Nasdaq Composite (EE. UU.).',
                'currency' => 'USD',
                'region' => 'usa',
            ],
            [
                'id' => 'nasdaq100',
                'name' => 'Nasdaq 100',
                'symbol' => '^NDX',
                'description' => 'Las 100 mayores tecnológicas del Nasdaq.',
                'currency' => 'USD',
                'region' => 'usa',
            ],
            [
                'id' => 'dow',
                'name' => 'Dow Jones',
                'symbol' => '^DJI',
                'description' => 'Dow Jones Industrial Average (EE. UU.).',
                'currency' => 'USD',
                'region' => 'usa',
            ],
            [
                'id' => 'sp500',
                'name' => 'S&P 500',
                'symbol' => '^GSPC',
                'description' => 'Standard & Poor\'s 500 (EE. UU.).',
                'currency' => 'USD',
                'region' => 'usa',
            ],
            [
                'id' => 'sp100',
                'name' => 'S&P 100',
                'symbol' => '^OEX',
                'description' => 'Las 100 mayores empresas del S&P 500.',
                'currency' => 'USD',
                'region' => 'usa',
            ],
            [
                'id' => 'sp400',
                'name' => 'S&P MidCap 400',
                'symbol' => '^MID',
                'description' => 'Mediana capitalización de EE. UU. (S&P).',
                'currency' => 'USD',
                'region' => 'usa',
            ],
            [
                'id' => 'sp600',
                'name' => 'S&P SmallCap 600',
                'symbol' => '^SP600',
                'description' => 'Pequeña capitalización de EE. UU. (S&P).',
                'currency' => 'USD',
                'region' => 'usa',
            ],
        ];
    }

    /** @return array{id: string, name: string, symbol: string, description: string, currency: string, region: string}|null */
    public static function find(string $id): ?array
    {
        foreach (self::all() as $index) {
            if ($index['id'] === $id) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public static function normalizeIds(array $ids): array
    {
        $valid = array_column(self::all(), 'id');
        $normalized = [];
        foreach ($ids as $id) {
            if (!is_string($id) || !in_array($id, $valid, true)) {
                continue;
            }
            if (!in_array($id, $normalized, true)) {
                $normalized[] = $id;
            }
        }

        return $normalized !== [] ? $normalized : self::DEFAULT_INDEX_IDS;
    }

    /**
     * @return list<string>
     */
    public static function groupOrder(): array
    {
        return ['spain', 'europe', 'usa'];
    }
}
