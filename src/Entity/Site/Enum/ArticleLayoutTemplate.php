<?php

namespace App\Entity\Site\Enum;

enum ArticleLayoutTemplate: string
{
    case Standard = 'standard';
    case Wide = 'wide';
    case Immersive = 'immersive';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Estándar — artículo + barra lateral',
            self::Wide => 'Amplio — contenido dominante, sidebar estrecha',
            self::Immersive => 'Inmersivo — hero a pantalla completa',
        };
    }
}
