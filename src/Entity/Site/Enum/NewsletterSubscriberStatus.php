<?php

namespace App\Entity\Site\Enum;

enum NewsletterSubscriberStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de confirmación',
            self::Confirmed => 'Activo',
            self::Unsubscribed => 'Baja',
        };
    }
}
