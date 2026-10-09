<?php

namespace App\ContentModules;

enum ContentModuleFormat: string
{
    case Default = 'default';
    case Grid2 = 'grid_2';
    case Grid3 = 'grid_3';
    case Grid4 = 'grid_4';
    case Grid6 = 'grid_6';
    case Grid8 = 'grid_8';
    case List = 'list';
    case List2Col = 'list_2col';
    case LeadStack = 'lead_stack';
    case Magazine = 'magazine';
    case HeroPair = 'hero_pair';
    case Headlines = 'headlines';
    case Mosaic = 'mosaic';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Predeterminado',
            self::Grid2 => 'Rejilla · 2 columnas',
            self::Grid3 => 'Rejilla · 3 columnas',
            self::Grid4 => 'Rejilla · 4 noticias',
            self::Grid6 => 'Rejilla · 6 noticias',
            self::Grid8 => 'Rejilla · 8 noticias',
            self::List => 'Lista vertical',
            self::List2Col => 'Lista en 2 columnas',
            self::LeadStack => 'Destacada + lista (estilo portada)',
            self::Magazine => 'Revista · 1 grande + 3',
            self::HeroPair => 'Doble portada · 2 grandes',
            self::Headlines => 'Titulares (solo texto)',
            self::Mosaic => 'Mosaico · 1 + 4',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Default => 'Formato fijo del módulo.',
            self::Grid2 => 'Dos tarjetas amplias con imagen y resumen.',
            self::Grid3 => 'Tres columnas equilibradas.',
            self::Grid4 => 'Cuatro noticias en rejilla.',
            self::Grid6 => 'Seis noticias en tres columnas.',
            self::Grid8 => 'Ocho noticias compactas.',
            self::List => 'Listado vertical con miniatura.',
            self::List2Col => 'Dos columnas de listados con imagen.',
            self::LeadStack => 'Una destacada y el resto en columna.',
            self::Magazine => 'Una noticia grande y tres al lado.',
            self::HeroPair => 'Dos piezas grandes a pantalla.',
            self::Headlines => 'Solo titulares y fecha, sin imagen.',
            self::Mosaic => 'Una ancha arriba y cuatro debajo.',
        };
    }

    public function gridColumns(): ?int
    {
        return match ($this) {
            self::Grid2, self::HeroPair => 2,
            self::Grid3, self::Grid6 => 3,
            self::Grid4, self::Grid8 => 4,
            default => null,
        };
    }

    public function suggestedCount(): int
    {
        return match ($this) {
            self::Grid2, self::HeroPair => 2,
            self::Grid3 => 3,
            self::Grid4, self::LeadStack, self::Magazine => 4,
            self::Mosaic => 5,
            self::Grid6, self::List, self::List2Col => 6,
            self::Grid8, self::Headlines => 8,
            self::Default => 0,
        };
    }
}
