<?php

namespace App\Entity\Site\Enum;

enum NewsletterFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Diario',
            self::Weekly => 'Semanal',
        };
    }

    public function periodSeconds(): int
    {
        return match ($this) {
            self::Daily => 86400,
            self::Weekly => 604800,
        };
    }
}
