<?php

namespace App\ContentModules;

enum ContentModuleType: string
{
    case Related = 'related';
    case Popular = 'popular';
    case Latest = 'latest';
    case Accommodations = 'accommodations';
    case CategorySections = 'category_sections';

    public function label(): string
    {
        return match ($this) {
            self::Related => 'Te puede interesar',
            self::Popular => 'Más vistas',
            self::Latest => 'Últimas noticias',
            self::Accommodations => 'Alojamientos',
            self::CategorySections => 'Secciones por categoría',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Related => 'Noticias relacionadas o de interés tras el contenido.',
            self::Popular => 'Las más leídas del periódico.',
            self::Latest => 'Las noticias más recientes.',
            self::Accommodations => 'Widget de alojamientos (configurado en Tiempo).',
            self::CategorySections => 'Bloques de categorías del menú (como en portada).',
        };
    }

    /** @return list<ContentModuleFormat> */
    public function allowedFormats(): array
    {
        return match ($this) {
            self::Accommodations => [ContentModuleFormat::Default],
            self::CategorySections => [
                ContentModuleFormat::LeadStack,
                ContentModuleFormat::Magazine,
                ContentModuleFormat::Grid3,
                ContentModuleFormat::Grid4,
            ],
            self::Related, self::Popular, self::Latest => [
                ContentModuleFormat::Grid2,
                ContentModuleFormat::Grid3,
                ContentModuleFormat::Grid4,
                ContentModuleFormat::Grid6,
                ContentModuleFormat::Grid8,
                ContentModuleFormat::List,
                ContentModuleFormat::List2Col,
                ContentModuleFormat::LeadStack,
                ContentModuleFormat::Magazine,
                ContentModuleFormat::HeroPair,
                ContentModuleFormat::Headlines,
                ContentModuleFormat::Mosaic,
            ],
        };
    }

    public function defaultFormat(): ContentModuleFormat
    {
        return match ($this) {
            self::Accommodations => ContentModuleFormat::Default,
            self::CategorySections => ContentModuleFormat::LeadStack,
            self::Related, self::Popular, self::Latest => ContentModuleFormat::Grid4,
        };
    }

    public function defaultCount(): int
    {
        return match ($this) {
            self::Related => 8,
            self::Popular, self::Latest => 4,
            self::Accommodations, self::CategorySections => 0,
        };
    }

    public function usesArticleList(): bool
    {
        return match ($this) {
            self::Related, self::Popular, self::Latest => true,
            default => false,
        };
    }
}
