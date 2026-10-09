<?php

namespace App\Editorial;

/**
 * Plantillas de texto para banners autogenerados.
 *
 * @phpstan-type Preset array{
 *     key: string,
 *     label: string,
 *     headline: string,
 *     body: string,
 *     cta: string,
 *     bgColor: string,
 *     textColor: string,
 *     accentColor: string
 * }
 */
final class AdBannerTextPresets
{
    /**
     * @return list<Preset>
     */
    public static function all(): array
    {
        return [
            self::preset(
                'advertise',
                'Anúnciate aquí',
                'Anúnciate en este medio',
                'Llega a lectores locales con una campaña sencilla y efectiva.',
                'Quiero anunciarme',
                '#0f172a',
                '#f8fafc',
                '#f59e0b',
            ),
            self::preset(
                'sponsor',
                'Patrocinio',
                'Patrocina esta sección',
                'Asocia tu marca a contenidos de calidad y audiencia fiel.',
                'Ser patrocinador',
                '#1e3a5f',
                '#ffffff',
                '#38bdf8',
            ),
            self::preset(
                'newsletter',
                'Boletín',
                'Recibe las noticias del día',
                'Suscríbete al boletín y no te pierdas lo más importante.',
                'Suscribirme',
                '#14532d',
                '#ecfdf5',
                '#86efac',
            ),
            self::preset(
                'business',
                'Negocio local',
                'Destaca tu negocio',
                'Publicidad local para comercios, servicios y profesionales.',
                'Publicar anuncio',
                '#7c2d12',
                '#fff7ed',
                '#fb923c',
            ),
            self::preset(
                'follow',
                'Síguenos',
                'Síguenos en redes',
                'Actualidad, alertas y cobertura especial en nuestras redes.',
                'Seguir ahora',
                '#312e81',
                '#eef2ff',
                '#a5b4fc',
            ),
            self::preset(
                'classifieds',
                'Avisos',
                'Publica tu aviso',
                'Compra, venta y servicios entre vecinos. Rápido y cercano.',
                'Crear aviso',
                '#334155',
                '#f1f5f9',
                '#94a3b8',
            ),
        ];
    }

    /**
     * @return Preset|null
     */
    public static function get(string $key): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return Preset
     */
    private static function preset(
        string $key,
        string $label,
        string $headline,
        string $body,
        string $cta,
        string $bgColor,
        string $textColor,
        string $accentColor,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'headline' => $headline,
            'body' => $body,
            'cta' => $cta,
            'bgColor' => $bgColor,
            'textColor' => $textColor,
            'accentColor' => $accentColor,
        ];
    }
}
