<?php

namespace App\Entity\Site\Enum;

enum AdPlacement: string
{
    case HeaderBelowNav = 'header_below_nav';
    case FooterAbove = 'footer_above';

    case HomeBelowHero = 'home_below_hero';
    case HomeMidContent = 'home_mid_content';
    case HomeSidebar = 'home_sidebar';

    case ArticleSidebar = 'article_sidebar';
    case ArticleAboveContent = 'article_above_content';
    case ArticleBelowContent = 'article_below_content';

    case SidebarTop = 'sidebar_top';
    case SidebarBetweenLists = 'sidebar_between_lists';
    case SidebarBottom = 'sidebar_bottom';

    case CategorySidebar = 'category_sidebar';
    case GamesSidebar = 'games_sidebar';
    case MarketsSidebar = 'markets_sidebar';
    case TvSidebar = 'tv_sidebar';
    case WeatherSponsor = 'weather_sponsor';
    case WeatherFooter = 'weather_footer';
    case WeatherSidebar = 'weather_sidebar';

    public function label(): string
    {
        return match ($this) {
            self::HeaderBelowNav => 'Global — bajo el menú',
            self::FooterAbove => 'Global — encima del pie',
            self::HomeBelowHero => 'Portada — bajo el hero',
            self::HomeMidContent => 'Portada — mitad de contenido',
            self::HomeSidebar => 'Portada — columna derecha',
            self::ArticleSidebar => 'Noticia — columna derecha (principal)',
            self::ArticleAboveContent => 'Noticia — encima del texto',
            self::ArticleBelowContent => 'Noticia — debajo del texto',
            self::SidebarTop => 'Columna derecha — arriba (varias páginas)',
            self::SidebarBetweenLists => 'Columna derecha — entre Más vistas y Últimas',
            self::SidebarBottom => 'Columna derecha — abajo (varias páginas)',
            self::CategorySidebar => 'Categoría / archivo — columna derecha',
            self::GamesSidebar => 'Pasatiempos — columna derecha',
            self::MarketsSidebar => 'Bolsa — columna derecha',
            self::TvSidebar => 'Guía TV — columna derecha',
            self::WeatherSponsor => 'El tiempo — patrocinio principal',
            self::WeatherFooter => 'El tiempo — pie de sección',
            self::WeatherSidebar => 'El tiempo — columna derecha',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::HeaderBelowNav, self::FooterAbove => 'Todo el periódico',
            self::HomeBelowHero, self::HomeMidContent, self::HomeSidebar => 'Portada',
            self::ArticleSidebar, self::ArticleAboveContent, self::ArticleBelowContent => 'Noticias (artículos)',
            self::SidebarTop, self::SidebarBetweenLists, self::SidebarBottom => 'Columna derecha compartida',
            self::CategorySidebar => 'Categorías y archivo',
            self::GamesSidebar, self::MarketsSidebar, self::TvSidebar,
            self::WeatherSponsor, self::WeatherFooter, self::WeatherSidebar => 'Secciones especiales',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::HeaderBelowNav => 'Bajo la navegación en casi todas las páginas.',
            self::FooterAbove => 'Justo antes del footer.',
            self::HomeBelowHero => 'Debajo del bloque principal de portada.',
            self::HomeMidContent => 'Entre bloques del cuerpo de portada.',
            self::HomeSidebar => 'Columna derecha de la portada.',
            self::ArticleSidebar => 'Lo más visible en la columna derecha de cada noticia.',
            self::ArticleAboveContent => 'Entre titular/galería y el cuerpo de la noticia.',
            self::ArticleBelowContent => 'Al final del contenido de la noticia.',
            self::SidebarTop => 'Arriba a la derecha en noticias, archivo, bolsa, TV…',
            self::SidebarBetweenLists => 'Entre «Más vistas» y «Últimas noticias» en la columna derecha.',
            self::SidebarBottom => 'Abajo a la derecha en noticias, archivo y portada.',
            self::CategorySidebar => 'Lateral de categorías, etiquetas, búsqueda y páginas estáticas.',
            self::GamesSidebar => 'Lateral de /pasatiempos y sus juegos.',
            self::MarketsSidebar => 'Lateral de la sección Bolsa.',
            self::TvSidebar => 'Lateral de la Guía TV.',
            self::WeatherSponsor => 'Cabecera de El tiempo.',
            self::WeatherFooter => 'Pie de El tiempo.',
            self::WeatherSidebar => 'Lateral de El tiempo.',
        };
    }

    /**
     * @return list<string>
     */
    public static function groupOrder(): array
    {
        return [
            'Noticias (artículos)',
            'Portada',
            'Columna derecha compartida',
            'Categorías y archivo',
            'Secciones especiales',
            'Todo el periódico',
        ];
    }

    /** @return list<AdPlacement> */
    public static function forPage(string $page): array
    {
        return match ($page) {
            'home' => [
                self::HeaderBelowNav,
                self::HomeBelowHero,
                self::HomeMidContent,
                self::HomeSidebar,
                self::SidebarTop,
                self::SidebarBetweenLists,
                self::SidebarBottom,
                self::FooterAbove,
            ],
            'article' => [
                self::HeaderBelowNav,
                self::ArticleSidebar,
                self::ArticleAboveContent,
                self::ArticleBelowContent,
                self::SidebarTop,
                self::SidebarBetweenLists,
                self::SidebarBottom,
                self::FooterAbove,
            ],
            'category' => [
                self::HeaderBelowNav,
                self::CategorySidebar,
                self::SidebarTop,
                self::SidebarBetweenLists,
                self::SidebarBottom,
                self::FooterAbove,
            ],
            'games' => [
                self::HeaderBelowNav,
                self::GamesSidebar,
                self::SidebarTop,
                self::SidebarBetweenLists,
                self::SidebarBottom,
                self::FooterAbove,
            ],
            'markets' => [
                self::HeaderBelowNav,
                self::MarketsSidebar,
                self::SidebarTop,
                self::SidebarBetweenLists,
                self::FooterAbove,
            ],
            'tv' => [
                self::HeaderBelowNav,
                self::TvSidebar,
                self::SidebarTop,
                self::SidebarBetweenLists,
                self::FooterAbove,
            ],
            'weather' => [
                self::HeaderBelowNav,
                self::WeatherSponsor,
                self::WeatherFooter,
                self::WeatherSidebar,
                self::CategorySidebar,
                self::SidebarBetweenLists,
                self::FooterAbove,
            ],
            default => self::cases(),
        };
    }
}
