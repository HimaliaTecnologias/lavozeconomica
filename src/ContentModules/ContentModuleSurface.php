<?php

namespace App\ContentModules;

enum ContentModuleSurface: string
{
    case Article = 'article';
    case Category = 'category';
    case StaticPage = 'static_page';
    case Contact = 'contact';
    case LocalServices = 'local_services';

    public function label(): string
    {
        return match ($this) {
            self::Article => 'Detalle de noticia',
            self::Category => 'Páginas de categoría / sección',
            self::StaticPage => 'Páginas institucionales',
            self::Contact => 'Contacto',
            self::LocalServices => 'Servicios (tráfico, farmacias…)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Article => 'Tras el cuerpo de la noticia (debajo de comentarios / engagement).',
            self::Category => 'Al final del listado de la categoría.',
            self::StaticPage => 'Quiénes somos, aviso legal, privacidad, cookies, anunciantes…',
            self::Contact => 'Página de contacto.',
            self::LocalServices => 'Tráfico, transporte, farmacias y teléfonos útiles.',
        };
    }

    /** @return list<ContentModuleType> */
    public function availableTypes(): array
    {
        return [
            ContentModuleType::Latest,
            ContentModuleType::Popular,
            ContentModuleType::Related,
            ContentModuleType::CategorySections,
            ContentModuleType::Accommodations,
        ];
    }
}
