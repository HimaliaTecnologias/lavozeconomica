<?php

namespace App\Security;

final class SiteRole
{
    public const SITE_ADMIN = 'site_admin';
    public const REDACTOR = 'redactor';
    /** @deprecated Usar {@see REDACTOR}; se mantiene por compatibilidad. */
    public const EDITOR = 'editor';
    public const READER = 'reader';

    /** @var list<string> */
    public const STAFF_ROLES = [self::SITE_ADMIN, self::REDACTOR, self::EDITOR];

    /** @var list<string> */
    public const ARTICLE_EDITOR_ROLES = [self::SITE_ADMIN, self::REDACTOR, self::EDITOR];

    /** @var array<string, string> */
    public const STAFF_LABELS = [
        self::SITE_ADMIN => 'Administrador del periódico',
        self::REDACTOR => 'Redactor',
        self::EDITOR => 'Redactor',
    ];

    public static function isStaff(string $role): bool
    {
        return in_array($role, self::STAFF_ROLES, true);
    }

    public static function canEditArticles(string $role): bool
    {
        return in_array($role, self::ARTICLE_EDITOR_ROLES, true);
    }

    public static function isSiteAdmin(string $role): bool
    {
        return $role === self::SITE_ADMIN;
    }
}
