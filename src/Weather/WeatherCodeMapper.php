<?php

namespace App\Weather;

final class WeatherCodeMapper
{
    /** @var array<int, string> */
    private const LABELS = [
        0 => 'Despejado',
        1 => 'Mayormente despejado',
        2 => 'Parcialmente nuboso',
        3 => 'Nuboso',
        45 => 'Niebla',
        48 => 'Niebla con escarcha',
        51 => 'Llovizna ligera',
        53 => 'Llovizna',
        55 => 'Llovizna intensa',
        56 => 'Llovizna helada ligera',
        57 => 'Llovizna helada',
        61 => 'Lluvia ligera',
        63 => 'Lluvia',
        65 => 'Lluvia intensa',
        66 => 'Lluvia helada ligera',
        67 => 'Lluvia helada',
        71 => 'Nieve ligera',
        73 => 'Nieve',
        75 => 'Nieve intensa',
        77 => 'Granizo',
        80 => 'Chubascos ligeros',
        81 => 'Chubascos',
        82 => 'Chubascos fuertes',
        85 => 'Chubascos de nieve ligeros',
        86 => 'Chubascos de nieve',
        95 => 'Tormenta',
        96 => 'Tormenta con granizo ligero',
        99 => 'Tormenta con granizo',
    ];

    public static function describe(int $code): string
    {
        return self::LABELS[$code] ?? 'Variable';
    }

    public static function iconClass(int $code): string
    {
        if ($code === 0) {
            return 'weather-icon--sun';
        }
        if ($code <= 3) {
            return 'weather-icon--cloud';
        }
        if ($code >= 51 && $code <= 67 || $code >= 80 && $code <= 82) {
            return 'weather-icon--rain';
        }
        if ($code >= 71 && $code <= 77 || $code >= 85 && $code <= 86) {
            return 'weather-icon--snow';
        }
        if ($code >= 95) {
            return 'weather-icon--storm';
        }
        if ($code >= 45 && $code <= 48) {
            return 'weather-icon--fog';
        }

        return 'weather-icon--cloud';
    }
}
