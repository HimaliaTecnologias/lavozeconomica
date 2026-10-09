<?php

namespace App\Markets;

final class MarketFormatter
{
    public static function formatPrice(?float $price, string $currency): string
    {
        if ($price === null) {
            return '—';
        }

        $decimals = $price >= 1000 ? 2 : ($price >= 100 ? 2 : 2);

        return number_format($price, $decimals, ',', '.').self::currencySuffix($currency);
    }

    public static function formatChange(?float $change, string $currency): string
    {
        if ($change === null) {
            return '—';
        }

        $prefix = $change > 0 ? '+' : '';

        return $prefix.number_format($change, 2, ',', '.').self::currencySuffix($currency);
    }

    public static function formatChangePercent(?float $changePercent): string
    {
        if ($changePercent === null) {
            return '—';
        }

        $prefix = $changePercent > 0 ? '+' : '';

        return $prefix.number_format($changePercent, 2, ',', '.').'%';
    }

    public static function changeClass(?float $change): string
    {
        if ($change === null || abs($change) < 0.0001) {
            return 'market-flat';
        }

        return $change > 0 ? 'market-up' : 'market-down';
    }

    /**
     * @param list<float|null> $points
     */
    public static function sparklinePath(array $points, int $width = 140, int $height = 44): string
    {
        $values = [];
        foreach ($points as $point) {
            if ($point !== null && is_finite($point)) {
                $values[] = (float) $point;
            }
        }

        if (count($values) < 2) {
            return '';
        }

        $min = min($values);
        $max = max($values);
        $range = $max - $min;
        if ($range < 0.0001) {
            $range = 1.0;
        }

        $padding = 4;
        $innerWidth = $width - ($padding * 2);
        $innerHeight = $height - ($padding * 2);
        $count = count($values);
        $segments = [];

        foreach ($values as $i => $value) {
            $x = $padding + ($innerWidth * $i / max(1, $count - 1));
            $y = $padding + $innerHeight - (($value - $min) / $range) * $innerHeight;
            $segments[] = round($x, 1).','.round($y, 1);
        }

        return 'M '.implode(' L ', $segments);
    }

    public static function regionLabel(string $region): string
    {
        return match ($region) {
            'spain' => 'España',
            'europe' => 'Europa',
            'usa' => 'Estados Unidos',
            default => 'Mercados',
        };
    }

    /**
     * @param list<float> $points
     */
    public static function sparklineTrendLabel(array $points): string
    {
        $values = [];
        foreach ($points as $point) {
            if (is_finite($point)) {
                $values[] = (float) $point;
            }
        }

        if (count($values) < 2) {
            return '';
        }

        $first = $values[0];
        $last = $values[count($values) - 1];
        $delta = $last - $first;
        $threshold = max(abs($first) * 0.002, 0.5);

        if (abs($delta) < $threshold) {
            return 'lateral';
        }

        return $delta > 0 ? 'alcista' : 'bajista';
    }

    private static function currencySuffix(string $currency): string
    {
        return match ($currency) {
            'EUR' => ' €',
            'USD' => ' $',
            'GBP' => ' £',
            'CHF' => ' CHF',
            default => ' '.$currency,
        };
    }
}
