<?php

namespace App\Editorial;

final class SiteFontRegistry
{
    public const DEFAULT_HEADING_FONT = 'inter';
    public const DEFAULT_BODY_FONT = 'inter';
    public const DEFAULT_ARTICLE_FONT = 'source-sans-3';

    /** @var array<string, string> */
    public const CATEGORY_LABELS = [
        'serif-editorial' => 'Serif editorial',
        'serif-lectura' => 'Serif lectura',
        'slab' => 'Slab / periódico',
        'sans-humanista' => 'Sans humanista',
        'sans-moderna' => 'Sans moderna',
        'sans-geometrica' => 'Sans geométrica',
        'display' => 'Display / impacto',
    ];

    /**
     * @return array<string, array{
     *     label: string,
     *     family: string,
     *     fallback: string,
     *     googleQuery: string,
     *     category: string,
     *     roles: list<string>,
     *     group: string
     * }>
     */
    public static function fonts(): array
    {
        $defs = [
            // —— Serif editorial (titulares / marca) ——
            'playfair-display' => ['Playfair Display', 'Georgia, serif', 'Playfair+Display:wght@600;700;800', 'serif-editorial', ['heading', 'article']],
            'cormorant-garamond' => ['Cormorant Garamond', 'Georgia, serif', 'Cormorant+Garamond:wght@500;600;700', 'serif-editorial', ['heading', 'article']],
            'fraunces' => ['Fraunces', 'Georgia, serif', 'Fraunces:opsz,wght@9..144,500;600;700', 'serif-editorial', ['heading', 'article']],
            'instrument-serif' => ['Instrument Serif', 'Georgia, serif', 'Instrument+Serif:wght@400', 'serif-editorial', ['heading', 'article']],
            'libre-baskerville' => ['Libre Baskerville', 'Georgia, serif', 'Libre+Baskerville:wght@400;700', 'serif-editorial', ['heading', 'article']],
            'eb-garamond' => ['EB Garamond', 'Georgia, serif', 'EB+Garamond:wght@500;600;700', 'serif-editorial', ['heading', 'article']],
            'old-standard-tt' => ['Old Standard TT', 'Georgia, serif', 'Old+Standard+TT:wght@400;700', 'serif-editorial', ['heading', 'article']],
            'cinzel' => ['Cinzel', 'Georgia, serif', 'Cinzel:wght@500;600;700', 'serif-editorial', ['heading']],
            'abril-fatface' => ['Abril Fatface', 'Georgia, serif', 'Abril+Fatface', 'serif-editorial', ['heading']],
            'young-serif' => ['Young Serif', 'Georgia, serif', 'Young+Serif', 'serif-editorial', ['heading', 'article']],

            // —— Serif lectura ——
            'merriweather' => ['Merriweather', 'Georgia, serif', 'Merriweather:wght@400;700;900', 'serif-lectura', ['heading', 'body', 'article']],
            'newsreader' => ['Newsreader', 'Georgia, serif', 'Newsreader:opsz,wght@6..72,400;600;700', 'serif-lectura', ['heading', 'article']],
            'source-serif-4' => ['Source Serif 4', 'Georgia, serif', 'Source+Serif+4:opsz,wght@8..60,400;600;700', 'serif-lectura', ['heading', 'body', 'article']],
            'lora' => ['Lora', 'Georgia, serif', 'Lora:wght@400;600;700', 'serif-lectura', ['heading', 'body', 'article']],
            'crimson-pro' => ['Crimson Pro', 'Georgia, serif', 'Crimson+Pro:wght@400;600;700', 'serif-lectura', ['heading', 'article']],
            'literata' => ['Literata', 'Georgia, serif', 'Literata:opsz,wght@7..72,400;600;700', 'serif-lectura', ['article', 'body']],
            'spectral' => ['Spectral', 'Georgia, serif', 'Spectral:wght@400;600;700', 'serif-lectura', ['heading', 'article']],
            'vollkorn' => ['Vollkorn', 'Georgia, serif', 'Vollkorn:wght@400;600;700', 'serif-lectura', ['heading', 'article']],
            'alegreya' => ['Alegreya', 'Georgia, serif', 'Alegreya:wght@400;500;700', 'serif-lectura', ['heading', 'article']],
            'pt-serif' => ['PT Serif', 'Georgia, serif', 'PT+Serif:wght@400;700', 'serif-lectura', ['heading', 'body', 'article']],
            'noto-serif' => ['Noto Serif', 'Georgia, serif', 'Noto+Serif:wght@400;600;700', 'serif-lectura', ['heading', 'body', 'article']],
            'cardo' => ['Cardo', 'Georgia, serif', 'Cardo:wght@400;700', 'serif-lectura', ['article']],
            'domine' => ['Domine', 'Georgia, serif', 'Domine:wght@400;600;700', 'serif-lectura', ['heading', 'article']],
            'libre-caslon-text' => ['Libre Caslon Text', 'Georgia, serif', 'Libre+Caslon+Text:wght@400;700', 'serif-lectura', ['article']],
            'ibm-plex-serif' => ['IBM Plex Serif', 'Georgia, serif', 'IBM+Plex+Serif:wght@400;500;600;700', 'serif-lectura', ['heading', 'body', 'article']],

            // —— Slab ——
            'roboto-slab' => ['Roboto Slab', 'Georgia, serif', 'Roboto+Slab:wght@400;600;700', 'slab', ['heading', 'body', 'article']],
            'bitter' => ['Bitter', 'Georgia, serif', 'Bitter:wght@400;600;700', 'slab', ['heading', 'body', 'article']],
            'zilla-slab' => ['Zilla Slab', 'Georgia, serif', 'Zilla+Slab:wght@400;600;700', 'slab', ['heading', 'body', 'article']],
            'arvo' => ['Arvo', 'Georgia, serif', 'Arvo:wght@400;700', 'slab', ['heading', 'body']],

            // —— Sans humanista ——
            'source-sans-3' => ['Source Sans 3', 'system-ui, sans-serif', 'Source+Sans+3:wght@400;600;700', 'sans-humanista', ['heading', 'body', 'article']],
            'open-sans' => ['Open Sans', 'system-ui, sans-serif', 'Open+Sans:wght@400;600;700', 'sans-humanista', ['heading', 'body', 'article']],
            'ibm-plex-sans' => ['IBM Plex Sans', 'system-ui, sans-serif', 'IBM+Plex+Sans:wght@400;500;600;700', 'sans-humanista', ['heading', 'body', 'article']],
            'pt-sans' => ['PT Sans', 'system-ui, sans-serif', 'PT+Sans:wght@400;700', 'sans-humanista', ['heading', 'body', 'article']],
            'noto-sans' => ['Noto Sans', 'system-ui, sans-serif', 'Noto+Sans:wght@400;500;600;700', 'sans-humanista', ['heading', 'body', 'article']],
            'alegreya-sans' => ['Alegreya Sans', 'system-ui, sans-serif', 'Alegreya+Sans:wght@400;500;700', 'sans-humanista', ['heading', 'body', 'article']],
            'libre-franklin' => ['Libre Franklin', 'system-ui, sans-serif', 'Libre+Franklin:wght@400;600;700;800', 'sans-humanista', ['heading', 'body', 'article']],
            'public-sans' => ['Public Sans', 'system-ui, sans-serif', 'Public+Sans:wght@400;500;600;700', 'sans-humanista', ['heading', 'body', 'article']],
            'fira-sans' => ['Fira Sans', 'system-ui, sans-serif', 'Fira+Sans:wght@400;500;600;700', 'sans-humanista', ['heading', 'body', 'article']],
            'nunito-sans' => ['Nunito Sans', 'system-ui, sans-serif', 'Nunito+Sans:wght@400;600;700', 'sans-humanista', ['heading', 'body', 'article']],

            // —— Sans moderna ——
            'inter' => ['Inter', 'system-ui, sans-serif', 'Inter:wght@400;500;600;700;800', 'sans-moderna', ['heading', 'body', 'article']],
            'roboto' => ['Roboto', 'system-ui, sans-serif', 'Roboto:wght@400;500;700', 'sans-moderna', ['heading', 'body', 'article']],
            'dm-sans' => ['DM Sans', 'system-ui, sans-serif', 'DM+Sans:wght@400;500;600;700', 'sans-moderna', ['heading', 'body', 'article']],
            'work-sans' => ['Work Sans', 'system-ui, sans-serif', 'Work+Sans:wght@400;500;600;700', 'sans-moderna', ['heading', 'body', 'article']],
            'manrope' => ['Manrope', 'system-ui, sans-serif', 'Manrope:wght@400;500;600;700;800', 'sans-moderna', ['heading', 'body', 'article']],
            'figtree' => ['Figtree', 'system-ui, sans-serif', 'Figtree:wght@400;500;600;700', 'sans-moderna', ['heading', 'body', 'article']],
            'outfit' => ['Outfit', 'system-ui, sans-serif', 'Outfit:wght@400;500;600;700', 'sans-moderna', ['heading', 'body', 'article']],
            'sora' => ['Sora', 'system-ui, sans-serif', 'Sora:wght@400;500;600;700', 'sans-moderna', ['heading', 'body']],
            'lexend' => ['Lexend', 'system-ui, sans-serif', 'Lexend:wght@400;500;600;700', 'sans-moderna', ['heading', 'body', 'article']],
            'instrument-sans' => ['Instrument Sans', 'system-ui, sans-serif', 'Instrument+Sans:wght@400;500;600;700', 'sans-moderna', ['heading', 'body', 'article']],
            'plus-jakarta-sans' => ['Plus Jakarta Sans', 'system-ui, sans-serif', 'Plus+Jakarta+Sans:wght@400;500;600;700', 'sans-moderna', ['heading', 'body', 'article']],

            // —— Sans geométrica ——
            'montserrat' => ['Montserrat', 'system-ui, sans-serif', 'Montserrat:wght@500;600;700;800', 'sans-geometrica', ['heading', 'body']],
            'poppins' => ['Poppins', 'system-ui, sans-serif', 'Poppins:wght@400;500;600;700', 'sans-geometrica', ['heading', 'body']],
            'raleway' => ['Raleway', 'system-ui, sans-serif', 'Raleway:wght@500;600;700;800', 'sans-geometrica', ['heading', 'body']],
            'josefin-sans' => ['Josefin Sans', 'system-ui, sans-serif', 'Josefin+Sans:wght@500;600;700', 'sans-geometrica', ['heading', 'body']],
            'space-grotesk' => ['Space Grotesk', 'system-ui, sans-serif', 'Space+Grotesk:wght@400;500;600;700', 'sans-geometrica', ['heading', 'body']],
            'rubik' => ['Rubik', 'system-ui, sans-serif', 'Rubik:wght@400;500;600;700', 'sans-geometrica', ['heading', 'body', 'article']],
            'barlow' => ['Barlow', 'system-ui, sans-serif', 'Barlow:wght@400;500;600;700', 'sans-geometrica', ['heading', 'body']],
            'cabin' => ['Cabin', 'system-ui, sans-serif', 'Cabin:wght@400;500;600;700', 'sans-geometrica', ['heading', 'body', 'article']],

            // —— Display / impacto ——
            'oswald' => ['Oswald', 'Arial, sans-serif', 'Oswald:wght@500;600;700', 'display', ['heading']],
            'bebas-neue' => ['Bebas Neue', 'Arial, sans-serif', 'Bebas+Neue', 'display', ['heading']],
            'archivo-black' => ['Archivo Black', 'Arial, sans-serif', 'Archivo+Black', 'display', ['heading']],
            'anton' => ['Anton', 'Arial, sans-serif', 'Anton', 'display', ['heading']],
            'teko' => ['Teko', 'Arial, sans-serif', 'Teko:wght@500;600;700', 'display', ['heading']],
            'passion-one' => ['Passion One', 'Arial, sans-serif', 'Passion+One:wght@400;700', 'display', ['heading']],
            'francois-one' => ['Francois One', 'Arial, sans-serif', 'Francois+One', 'display', ['heading']],
            'yanone-kaffeesatz' => ['Yanone Kaffeesatz', 'Arial, sans-serif', 'Yanone+Kaffeesatz:wght@500;600;700', 'display', ['heading']],
        ];

        $fonts = [];
        foreach ($defs as $slug => [$label, $fallback, $googleQuery, $category, $roles]) {
            $fonts[$slug] = [
                'label' => $label,
                'family' => $label,
                'fallback' => $fallback,
                'googleQuery' => $googleQuery,
                'category' => $category,
                'roles' => $roles,
                // Compatibilidad con plantillas que aún miran "group"
                'group' => self::legacyGroup($roles),
            ];
        }

        return $fonts;
    }

    /** @param list<string> $roles */
    private static function legacyGroup(array $roles): string
    {
        $hasHeading = \in_array('heading', $roles, true);
        $hasBody = \in_array('body', $roles, true);
        $hasArticle = \in_array('article', $roles, true);

        if ($hasHeading && $hasBody && $hasArticle) {
            return 'both';
        }
        if ($hasHeading && $hasBody) {
            return 'both';
        }
        if ($hasArticle && !$hasHeading && !$hasBody) {
            return 'article';
        }
        if ($hasHeading && !$hasBody) {
            return 'heading';
        }
        if ($hasBody) {
            return 'body';
        }

        return 'heading';
    }

    /** @return array<string, array{label: string, family: string, fallback: string, googleQuery: string, category: string, roles: list<string>, group: string}> */
    public static function fontsForRole(string $role): array
    {
        return array_filter(
            self::fonts(),
            static fn (array $font): bool => \in_array($role, $font['roles'], true),
        );
    }

    /**
     * Fuentes agrupadas por categoría para un rol (optgroups en selects).
     *
     * @return array<string, array<string, array{label: string, family: string, fallback: string, googleQuery: string, category: string, roles: list<string>, group: string}>>
     */
    public static function fontsGroupedForRole(string $role): array
    {
        $grouped = [];
        foreach (self::fontsForRole($role) as $slug => $font) {
            $grouped[$font['category']][$slug] = $font;
        }

        $ordered = [];
        foreach (array_keys(self::CATEGORY_LABELS) as $category) {
            if (isset($grouped[$category])) {
                $ordered[$category] = $grouped[$category];
            }
        }

        return $ordered;
    }

    public static function isValid(string $slug): bool
    {
        return isset(self::fonts()[$slug]);
    }

    public static function isValidForRole(string $slug, string $role): bool
    {
        $font = self::get($slug);

        return $font !== null && \in_array($role, $font['roles'], true);
    }

    /** @return array{label: string, family: string, fallback: string, googleQuery: string, category: string, roles: list<string>, group: string}|null */
    public static function get(string $slug): ?array
    {
        return self::fonts()[$slug] ?? null;
    }

    public static function cssFamily(string $slug): string
    {
        $font = self::get($slug);
        if ($font === null) {
            return 'system-ui, sans-serif';
        }

        return '"'.$font['family'].'", '.$font['fallback'];
    }

    /** @param list<string> $slugs */
    public static function buildGoogleFontsUrl(array $slugs): string
    {
        $unique = [];
        foreach ($slugs as $slug) {
            $font = self::get($slug);
            if ($font !== null) {
                $unique[$font['googleQuery']] = true;
            }
        }

        if ($unique === []) {
            return 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap';
        }

        $parts = [];
        foreach (array_keys($unique) as $query) {
            $parts[] = 'family='.$query;
        }

        return 'https://fonts.googleapis.com/css2?'.implode('&', $parts).'&display=swap';
    }
}
