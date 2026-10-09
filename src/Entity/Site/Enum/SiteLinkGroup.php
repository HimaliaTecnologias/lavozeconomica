<?php

namespace App\Entity\Site\Enum;

enum SiteLinkGroup: string
{
    case OtherMedia = 'other_media';
    case Interest = 'interest';

    public function label(): string
    {
        return match ($this) {
            self::OtherMedia => 'Otros medios',
            self::Interest => 'Enlaces de interés',
        };
    }
}
