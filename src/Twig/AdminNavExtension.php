<?php

namespace App\Twig;

use App\Admin\AdminNavBadges;
use App\Core\Site\SiteContextHolder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminNavExtension extends AbstractExtension
{
    public function __construct(
        private readonly SiteContextHolder $siteHolder,
        private readonly AdminNavBadges $navBadges,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_nav_badges', $this->navBadges(...)),
        ];
    }

    /** @return array{ingest_pending: int, draft_articles: int, comments: int} */
    public function navBadges(): array
    {
        $site = $this->siteHolder->get();
        if ($site === null) {
            return ['ingest_pending' => 0, 'draft_articles' => 0, 'comments' => 0];
        }

        return $this->navBadges->forSite($site);
    }
}
