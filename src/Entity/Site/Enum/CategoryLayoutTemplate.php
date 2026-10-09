<?php

namespace App\Entity\Site\Enum;

enum CategoryLayoutTemplate: string
{
    case Grid = 'grid';
    case List = 'list';
    case Magazine = 'magazine';

    public function label(): string
    {
        return match ($this) {
            self::Grid => 'Grid — mosaico de tarjetas',
            self::List => 'Lista — filas con miniatura',
            self::Magazine => 'Revista — noticia principal + grid',
        };
    }
}
