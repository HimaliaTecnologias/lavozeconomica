<?php

namespace App\Security;

/**
 * Capacidades del panel por periódico. Se asignan a cada tipo de usuario (rol).
 */
final class SitePermission
{
    public const DASHBOARD = 'dashboard';
    public const ARTICLES = 'articles';
    public const MEDIA = 'media';
    public const COMMENTS = 'comments';
    public const COMMUNITY = 'community';
    public const CATEGORIES = 'categories';
    public const POLLS = 'polls';
    public const GALLERIES = 'galleries';
    public const ADS = 'ads';
    public const INGEST = 'ingest';
    public const NEWSLETTER = 'newsletter';
    public const USERS = 'users';
    public const SETTINGS = 'settings';
    public const LINKS = 'links';

    /** @var list<string> */
    public const ALL = [
        self::DASHBOARD,
        self::ARTICLES,
        self::MEDIA,
        self::COMMENTS,
        self::COMMUNITY,
        self::CATEGORIES,
        self::POLLS,
        self::GALLERIES,
        self::ADS,
        self::INGEST,
        self::NEWSLETTER,
        self::USERS,
        self::SETTINGS,
        self::LINKS,
    ];

    /** @var array<string, string> */
    public const LABELS = [
        self::DASHBOARD => 'Panel / estadísticas',
        self::ARTICLES => 'Noticias',
        self::MEDIA => 'Biblioteca de medios',
        self::COMMENTS => 'Moderar comentarios',
        self::COMMUNITY => 'Comunidad (clasificados / foro)',
        self::CATEGORIES => 'Categorías',
        self::POLLS => 'Encuestas',
        self::GALLERIES => 'Galerías',
        self::ADS => 'Publicidad',
        self::INGEST => 'Ingesta HimtecMedia',
        self::NEWSLETTER => 'Boletín',
        self::USERS => 'Usuarios y permisos',
        self::SETTINGS => 'Configuración del sitio',
        self::LINKS => 'Enlaces',
    ];

    /** @var array<string, list<string>> */
    public const GROUPS = [
        'Editorial' => [
            self::DASHBOARD,
            self::ARTICLES,
            self::MEDIA,
            self::COMMENTS,
            self::CATEGORIES,
            self::POLLS,
            self::GALLERIES,
        ],
        'Monetización' => [
            self::ADS,
        ],
        'Distribución' => [
            self::INGEST,
            self::NEWSLETTER,
        ],
        'Sitio' => [
            self::COMMUNITY,
            self::USERS,
            self::SETTINGS,
            self::LINKS,
        ],
    ];

    /** Capacidades que el administrador del periódico no puede perder. */
    /** @var list<string> */
    public const LOCKED_FOR_SITE_ADMIN = [
        self::USERS,
        self::SETTINGS,
    ];

    /**
     * Matriz por defecto (comportamiento histórico).
     *
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        return [
            SiteRole::SITE_ADMIN => self::ALL,
            SiteRole::REDACTOR => [self::ARTICLES, self::MEDIA],
            SiteRole::EDITOR => [self::ARTICLES, self::MEDIA],
        ];
    }

    public static function label(string $permission): string
    {
        return self::LABELS[$permission] ?? $permission;
    }

    public static function isValid(string $permission): bool
    {
        return \in_array($permission, self::ALL, true);
    }

    /**
     * @param list<string> $permissions
     *
     * @return list<string>
     */
    public static function normalizeList(array $permissions): array
    {
        $out = [];
        foreach ($permissions as $permission) {
            if (!\is_string($permission) || !self::isValid($permission)) {
                continue;
            }
            $out[$permission] = true;
        }

        return array_keys($out);
    }
}
