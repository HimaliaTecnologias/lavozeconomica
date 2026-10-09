<?php

namespace App\Security;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Platform\Site;
use App\Entity\Platform\User;
use App\Entity\Platform\UserSiteRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SiteAuthorizationChecker
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteSettingsReader $settingsReader,
    ) {}

    public function getSiteRole(User $user, SiteContext $context): ?string
    {
        if ($user->isPlatformSuperadmin()) {
            return SiteRole::SITE_ADMIN;
        }

        $assignment = $this->findAssignment($user, $context);

        return $assignment?->getRole();
    }

    public function hasPermission(User $user, SiteContext $context, string $permission): bool
    {
        if (!SitePermission::isValid($permission)) {
            return false;
        }

        if ($user->isPlatformSuperadmin()) {
            return true;
        }

        $role = $this->getSiteRole($user, $context);
        if ($role === null || !SiteRole::isStaff($role)) {
            return false;
        }

        return $this->settingsReader->getPermissionsConfig($context)->roleHas($role, $permission);
    }

    /**
     * @param list<string> $permissions
     */
    public function hasAnyPermission(User $user, SiteContext $context, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $context, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function canAccessAdminPanel(User $user, SiteContext $context): bool
    {
        if ($user->isPlatformSuperadmin()) {
            return true;
        }

        $role = $this->getSiteRole($user, $context);
        if ($role === null || !SiteRole::isStaff($role)) {
            return false;
        }

        return $this->settingsReader->getPermissionsConfig($context)->forRole($role) !== [];
    }

    /** Acceso a configuración / gestión “de sitio” (compatibilidad). */
    public function canManageSite(User $user, SiteContext $context): bool
    {
        return $this->hasAnyPermission($user, $context, [
            SitePermission::USERS,
            SitePermission::SETTINGS,
        ]);
    }

    public function canEditArticles(User $user, SiteContext $context): bool
    {
        return $this->hasPermission($user, $context, SitePermission::ARTICLES);
    }

    public function isRedactorOnly(User $user, SiteContext $context): bool
    {
        if ($user->isPlatformSuperadmin()) {
            return false;
        }

        return $this->canEditArticles($user, $context)
            && !$this->hasPermission($user, $context, SitePermission::USERS)
            && !$this->hasPermission($user, $context, SitePermission::SETTINGS);
    }

    public function canEngageAsReader(User $user, SiteContext $context): bool
    {
        if ($user->isPlatformSuperadmin()) {
            return true;
        }

        $role = $this->getSiteRole($user, $context);
        if ($role === null) {
            return false;
        }

        return $role === SiteRole::READER || SiteRole::isStaff($role);
    }

    public function preferredAdminHome(User $user, SiteContext $context): string
    {
        if ($this->hasPermission($user, $context, SitePermission::DASHBOARD)) {
            return 'admin_dashboard';
        }
        if ($this->hasPermission($user, $context, SitePermission::ARTICLES)) {
            return 'admin_articles_index';
        }
        if ($this->hasPermission($user, $context, SitePermission::MEDIA)) {
            return 'admin_media_index';
        }

        foreach (SitePermission::ALL as $permission) {
            if (!$this->hasPermission($user, $context, $permission)) {
                continue;
            }

            return match ($permission) {
                SitePermission::COMMENTS => 'admin_engagement_comments',
                SitePermission::COMMUNITY => 'admin_community_classifieds',
                SitePermission::CATEGORIES => 'admin_categories_index',
                SitePermission::POLLS => 'admin_polls_index',
                SitePermission::GALLERIES => 'admin_galleries_index',
                SitePermission::ADS => 'admin_ads_index',
                SitePermission::INGEST => 'admin_ingest_index',
                SitePermission::NEWSLETTER => 'admin_newsletter_index',
                SitePermission::USERS => 'admin_users_index',
                SitePermission::SETTINGS => 'admin_settings_index',
                SitePermission::LINKS => 'admin_links_index',
                default => 'admin_articles_index',
            };
        }

        return 'admin_articles_index';
    }

    private function findAssignment(User $user, SiteContext $context): ?UserSiteRole
    {
        $site = $this->platformEm->find(Site::class, $context->id);
        if ($site === null) {
            return null;
        }

        return $this->platformEm->getRepository(UserSiteRole::class)->findOneBy([
            'user' => $user,
            'site' => $site,
        ]);
    }
}
