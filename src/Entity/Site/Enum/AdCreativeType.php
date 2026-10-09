<?php

namespace App\Entity\Site\Enum;

enum AdCreativeType: string
{
    case Image = 'image';
    case Text = 'text';
    case Generated = 'generated';
    case Html = 'html';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Imagen',
            self::Text => 'Texto',
            self::Generated => 'Autogenerado',
            self::Html => 'HTML / AdSense',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Image => 'Banner clásico con imagen y enlace.',
            self::Text => 'Bloque de texto editable (titular, mensaje y botón).',
            self::Generated => 'Plantilla lista con textos básicos; puedes ajustar el contenido.',
            self::Html => 'Código HTML personalizado o de redes publicitarias.',
        };
    }
}
