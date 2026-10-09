<?php

namespace App\Twig;

use App\Core\Site\SiteContextHolder;
use App\Entity\Platform\User;
use App\Security\SiteAuthorizationChecker;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminAuthorizationExtension extends AbstractExtension
{
    public function __construct(
        private readonly SiteContextHolder $siteHolder,
        private readonly SiteAuthorizationChecker $authorizationChecker,
        private readonly Security $security,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('can_manage_site', $this->canManageSite(...)),
            new TwigFunction('can_edit_articles_only', $this->canEditArticlesOnly(...)),
            new TwigFunction('can_edit_articles', $this->canEditArticles(...)),
            new TwigFunction('admin_can', $this->adminCan(...)),
        ];
    }

    public function adminCan(string $permission): bool
    {
        $user = $this->security->getUser();
        $site = $this->siteHolder->get();
        if (!$user instanceof User || $site === null) {
            return false;
        }

        return $this->authorizationChecker->hasPermission($user, $site, $permission);
    }

    public function canManageSite(): bool
    {
        $user = $this->security->getUser();
        $site = $this->siteHolder->get();
        if (!$user instanceof User || $site === null) {
            return false;
        }

        return $this->authorizationChecker->canManageSite($user, $site);
    }

    public function canEditArticlesOnly(): bool
    {
        $user = $this->security->getUser();
        $site = $this->siteHolder->get();
        if (!$user instanceof User || $site === null) {
            return false;
        }

        return $this->authorizationChecker->isRedactorOnly($user, $site);
    }

    public function canEditArticles(): bool
    {
        $user = $this->security->getUser();
        $site = $this->siteHolder->get();
        if (!$user instanceof User || $site === null) {
            return false;
        }

        return $this->authorizationChecker->canEditArticles($user, $site);
    }
}
