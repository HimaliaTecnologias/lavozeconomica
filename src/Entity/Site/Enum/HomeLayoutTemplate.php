<?php

namespace App\Entity\Site\Enum;

enum HomeLayoutTemplate: string
{
    case Classic = 'classic';
    case Magazine = 'magazine';
    case Compact = 'compact';

    public function label(): string
    {
        return match ($this) {
            self::Classic => 'Clásica — hero + destacadas + secciones',
            self::Magazine => 'Revista — portada visual con bloques amplios',
            self::Compact => 'Compacta — listado editorial con hero reducido',
        };
    }
}
