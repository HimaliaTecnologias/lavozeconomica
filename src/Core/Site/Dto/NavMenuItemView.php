<?php

namespace App\Core\Site\Dto;

final readonly class NavMenuItemView
{
    /**
     * @param list<NavMenuItemView> $children
     * @param list<string> $activeRoutes
     */
    public function __construct(
        public string $label,
        public string $url,
        public string $route = '',
        public ?string $routeSlug = null,
        public array $activeRoutes = [],
        public array $children = [],
        public string $cssClass = '',
        public bool $openInNewTab = false,
        public bool $isDropdown = false,
    ) {}
}
