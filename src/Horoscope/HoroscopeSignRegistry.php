<?php

namespace App\Horoscope;

final class HoroscopeSignRegistry
{
    /**
     * @return list<array{slug: string, name: string, symbol: string, dateRange: string}>
     */
    public static function all(): array
    {
        return [
            ['slug' => 'aries', 'name' => 'Aries', 'symbol' => '♈', 'dateRange' => '21 mar – 19 abr'],
            ['slug' => 'tauro', 'name' => 'Tauro', 'symbol' => '♉', 'dateRange' => '20 abr – 20 may'],
            ['slug' => 'geminis', 'name' => 'Géminis', 'symbol' => '♊', 'dateRange' => '21 may – 20 jun'],
            ['slug' => 'cancer', 'name' => 'Cáncer', 'symbol' => '♋', 'dateRange' => '21 jun – 22 jul'],
            ['slug' => 'leo', 'name' => 'Leo', 'symbol' => '♌', 'dateRange' => '23 jul – 22 ago'],
            ['slug' => 'virgo', 'name' => 'Virgo', 'symbol' => '♍', 'dateRange' => '23 ago – 21 sep'],
            ['slug' => 'libra', 'name' => 'Libra', 'symbol' => '♎', 'dateRange' => '22 sep – 22 oct'],
            ['slug' => 'escorpio', 'name' => 'Escorpio', 'symbol' => '♏', 'dateRange' => '23 oct – 21 nov'],
            ['slug' => 'sagitario', 'name' => 'Sagitario', 'symbol' => '♐', 'dateRange' => '22 nov – 21 dic'],
            ['slug' => 'capricornio', 'name' => 'Capricornio', 'symbol' => '♑', 'dateRange' => '22 dic – 19 ene'],
            ['slug' => 'acuario', 'name' => 'Acuario', 'symbol' => '♒', 'dateRange' => '20 ene – 18 feb'],
            ['slug' => 'piscis', 'name' => 'Piscis', 'symbol' => '♓', 'dateRange' => '19 feb – 20 mar'],
        ];
    }

    /** @return array{slug: string, name: string, symbol: string, dateRange: string}|null */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $sign) {
            if ($sign['slug'] === $slug) {
                return $sign;
            }
        }

        return null;
    }
}
