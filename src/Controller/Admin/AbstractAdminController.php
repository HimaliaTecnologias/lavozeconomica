<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteContextHolder;
use App\Entity\Platform\User;
use App\Security\SiteAuthorizationChecker;
use App\Security\SitePermission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

abstract class AbstractAdminController extends AbstractController
{
    public function __construct(
        protected readonly SiteContextHolder $siteHolder,
        protected readonly SiteAuthorizationChecker $authorizationChecker,
    ) {}

    /**
     * @return array{0: SiteContext, 1: User}
     */
    protected function requirePermission(string $permission): array
    {
        $site = $this->siteHolder->require();
        $user = $this->requireAuthenticatedUser();

        if (!$this->authorizationChecker->hasPermission($user, $site, $permission)) {
            throw $this->createAccessDeniedException('No tienes permiso para esta sección del panel.');
        }

        return [$site, $user];
    }

    /** @return array{0: SiteContext, 1: User} */
    protected function requireSiteAccess(): array
    {
        return $this->requirePermission(SitePermission::SETTINGS);
    }

    /** @return array{0: SiteContext, 1: User} */
    protected function requireFullAdminAccess(): array
    {
        return $this->requirePermission(SitePermission::USERS);
    }

    /** @return array{0: SiteContext, 1: User} */
    protected function requireArticleEditorAccess(): array
    {
        return $this->requirePermission(SitePermission::ARTICLES);
    }

    private function requireAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
