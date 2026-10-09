<?php

namespace App\Core\Site\Dto;

use App\Security\SitePermission;
use App\Security\SiteRole;

/**
 * Matriz de permisos por rol de personal del periódico.
 */
final readonly class SitePermissionsConfig
{
    /**
     * @param array<string, list<string>> $matrix role => permissions
     */
    public function __construct(
        public array $matrix = [],
    ) {}

    public static function fromDefaults(): self
    {
        return new self(SitePermission::defaults());
    }

    /**
     * @param mixed $raw
     */
    public static function fromJson(mixed $raw): self
    {
        $defaults = SitePermission::defaults();
        if (!\is_string($raw) || trim($raw) === '') {
            return new self($defaults);
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new self($defaults);
        }

        if (!\is_array($decoded)) {
            return new self($defaults);
        }

        $matrix = $defaults;
        foreach ([SiteRole::SITE_ADMIN, SiteRole::REDACTOR, SiteRole::EDITOR] as $role) {
            if (!isset($decoded[$role]) || !\is_array($decoded[$role])) {
                continue;
            }
            $matrix[$role] = SitePermission::normalizeList($decoded[$role]);
        }

        // El administrador no puede quedarse sin usuarios/configuración.
        foreach (SitePermission::LOCKED_FOR_SITE_ADMIN as $locked) {
            if (!\in_array($locked, $matrix[SiteRole::SITE_ADMIN], true)) {
                $matrix[SiteRole::SITE_ADMIN][] = $locked;
            }
        }

        // editor hereda de redactor si no viene explícito en JSON viejo
        if (!isset($decoded[SiteRole::EDITOR]) && isset($matrix[SiteRole::REDACTOR])) {
            $matrix[SiteRole::EDITOR] = $matrix[SiteRole::REDACTOR];
        }

        return new self($matrix);
    }

    /**
     * @return list<string>
     */
    public function forRole(string $role): array
    {
        if ($role === SiteRole::EDITOR) {
            return $this->matrix[SiteRole::EDITOR]
                ?? $this->matrix[SiteRole::REDACTOR]
                ?? SitePermission::defaults()[SiteRole::REDACTOR];
        }

        return $this->matrix[$role] ?? SitePermission::defaults()[$role] ?? [];
    }

    public function roleHas(string $role, string $permission): bool
    {
        return \in_array($permission, $this->forRole($role), true);
    }

    /**
     * @return array<string, list<string>>
     */
    public function toMatrix(): array
    {
        return [
            SiteRole::SITE_ADMIN => $this->forRole(SiteRole::SITE_ADMIN),
            SiteRole::REDACTOR => $this->forRole(SiteRole::REDACTOR),
            SiteRole::EDITOR => $this->forRole(SiteRole::EDITOR),
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toMatrix(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
