<?php

namespace App\Tv;

final class TvFormatter
{
    public static function formatTime(\DateTimeImmutable $time): string
    {
        return $time->format('H:i');
    }

    public static function formatTimeRange(\DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        return self::formatTime($start).' – '.self::formatTime($end);
    }

    public static function formatDuration(\DateTimeImmutable $start, \DateTimeImmutable $end): string
    {
        $minutes = max(1, (int) round(($end->getTimestamp() - $start->getTimestamp()) / 60));

        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest > 0 ? sprintf('%d h %d min', $hours, $rest) : sprintf('%d h', $hours);
    }
}
