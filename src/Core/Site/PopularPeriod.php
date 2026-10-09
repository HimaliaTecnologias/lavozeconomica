<?php

namespace App\Core\Site;

enum PopularPeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public static function tryFromRequest(?string $value, self $default = self::Week): self
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return self::tryFrom($value) ?? $default;
    }

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Día',
            self::Week => 'Semana',
            self::Month => 'Mes',
            self::Year => 'Año',
        };
    }

    public function since(?\DateTimeZone $timezone = null): \DateTimeImmutable
    {
        $tz = $timezone ?? new \DateTimeZone('Europe/Madrid');
        $now = new \DateTimeImmutable('now', $tz);

        return match ($this) {
            self::Day => $now->setTime(0, 0),
            self::Week => $now->modify('-7 days')->setTime(0, 0),
            self::Month => $now->modify('-30 days')->setTime(0, 0),
            self::Year => $now->modify('-365 days')->setTime(0, 0),
        };
    }

    /**
     * @return list<self>
     */
    public static function casesInOrder(): array
    {
        return [self::Day, self::Week, self::Month, self::Year];
    }
}
