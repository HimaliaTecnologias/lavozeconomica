<?php

namespace App\Editorial;

final class SitePageRegistry
{
    public const SLUG_PATTERN = 'quienes-somos|aviso-legal|privacidad|cookies|contacto|anunciantes';

    /** @return array<string, array{title: string, settingKey: string}> */
    public static function pages(): array
    {
        return [
            'quienes-somos' => [
                'title' => 'Quiénes somos',
                'settingKey' => 'quienes_somos_html',
            ],
            'aviso-legal' => [
                'title' => 'Aviso legal',
                'settingKey' => 'aviso_legal_html',
            ],
            'privacidad' => [
                'title' => 'Política de privacidad',
                'settingKey' => 'privacidad_html',
            ],
            'cookies' => [
                'title' => 'Política de cookies',
                'settingKey' => 'cookies_html',
            ],
            'contacto' => [
                'title' => 'Contacto',
                'settingKey' => 'contacto_html',
            ],
            'anunciantes' => [
                'title' => 'Anunciantes',
                'settingKey' => 'anunciantes_html',
            ],
        ];
    }

    public static function isValidSlug(string $slug): bool
    {
        return isset(self::pages()[$slug]);
    }

    /** @return array{title: string, settingKey: string}|null */
    public static function get(string $slug): ?array
    {
        return self::pages()[$slug] ?? null;
    }
}
