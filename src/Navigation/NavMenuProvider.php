<?php

namespace App\Navigation;

use App\Core\Site\Dto\NavMenuConfig;
use App\Core\Site\Dto\NavMenuItemView;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Site\Category;
use App\Editorial\SitePageRegistry;
use App\Repository\Site\CategoryRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class NavMenuProvider
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    /** @return list<NavMenuItemView> */
    public function buildMain(SiteContext $context): array
    {
        $config = $this->settingsReader->getNavMenuConfig($context);
        $categories = $this->loadNavCategories($context);
        $items = $this->buildFromItems($context, $config->mainItems, $categories, forMainNav: true);

        return $this->groupMainServices($context, $items, $config->mainItems);
    }

    /** @return list<NavMenuItemView> */
    public function buildFooterSections(SiteContext $context): array
    {
        $config = $this->settingsReader->getNavMenuConfig($context);
        $categories = $this->loadNavCategories($context);

        return $this->buildFromItems($context, $config->footerSectionItems, $categories, forMainNav: false);
    }

    /** @return list<NavMenuItemView> */
    public function buildFooterLegal(SiteContext $context): array
    {
        $config = $this->settingsReader->getNavMenuConfig($context);
        $categories = $this->loadNavCategories($context);

        return $this->buildFromItems($context, $config->footerLegalItems, $categories, forMainNav: false);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<Category> $navCategories
     *
     * @return list<NavMenuItemView>
     */
    private function buildFromItems(
        SiteContext $context,
        array $items,
        array $navCategories,
        bool $forMainNav,
    ): array
    {
        $weatherConfig = $this->settingsReader->getWeatherConfig($context);
        $marketsConfig = $this->settingsReader->getMarketConfig($context);
        $tvConfig = $this->settingsReader->getTvConfig($context);
        $gamesConfig = $this->settingsReader->getGamesConfig($context);
        $horoscopeConfig = $this->settingsReader->getHoroscopeConfig($context);
        $classifiedsConfig = $this->settingsReader->getClassifiedsConfig($context);
        $forumConfig = $this->settingsReader->getForumConfig($context);
        $isAuthenticated = $this->tokenStorage->getToken()?->getUser() !== null;
        $allCategories = $this->loadAllCategories($context);
        $result = [];

        foreach ($items as $item) {
            if (!($item['enabled'] ?? false)) {
                continue;
            }

            $type = NavMenuItemType::tryFrom((string) ($item['type'] ?? ''));
            if ($type === null) {
                continue;
            }

            match ($type) {
                NavMenuItemType::Categories => $this->appendCategoryItems($result, $navCategories, $forMainNav),
                NavMenuItemType::Login => $isAuthenticated ? null : $this->appendIfNotNull($result, $this->loginItem($item, $forMainNav)),
                NavMenuItemType::Register => $isAuthenticated ? null : $this->appendIfNotNull($result, $this->registerItem($item, $forMainNav)),
                NavMenuItemType::Profile => !$isAuthenticated ? null : $this->appendIfNotNull($result, $this->profileItem($item, $forMainNav)),
                NavMenuItemType::Logout => !$isAuthenticated ? null : $this->appendIfNotNull($result, $this->logoutItem($item, $forMainNav)),
                NavMenuItemType::Weather => $weatherConfig->enabled && $weatherConfig->isConfigured()
                    ? $this->appendIfNotNull($result, $this->weatherItem($item, $forMainNav))
                    : null,
                NavMenuItemType::Markets => $marketsConfig->enabled && $marketsConfig->isConfigured()
                    ? $this->appendIfNotNull($result, $this->marketsItem($item, $forMainNav))
                    : null,
                NavMenuItemType::Tv => $tvConfig->enabled && $tvConfig->isConfigured()
                    ? $this->appendIfNotNull($result, $this->tvItem($item, $forMainNav))
                    : null,
                NavMenuItemType::Games => $gamesConfig->enabled && $gamesConfig->isConfigured()
                    ? $this->appendIfNotNull($result, $this->gamesItem($item, $forMainNav))
                    : null,
                NavMenuItemType::Horoscope => $horoscopeConfig->enabled && $horoscopeConfig->isConfigured()
                    ? $this->appendIfNotNull($result, $this->horoscopeItem($item, $forMainNav))
                    : null,
                NavMenuItemType::Classifieds => $classifiedsConfig->enabled && $classifiedsConfig->isConfigured()
                    ? $this->appendIfNotNull($result, $this->classifiedsItem($item, $forMainNav))
                    : null,
                NavMenuItemType::Forum => $forumConfig->enabled && $forumConfig->isConfigured()
                    ? $this->appendIfNotNull($result, $this->forumItem($item, $forMainNav))
                    : null,
                NavMenuItemType::Services => null, // Se materializa en groupMainServices() solo para el menú principal.
                NavMenuItemType::Category => $this->appendIfNotNull(
                    $result,
                    $this->categoryItem($item, $allCategories, $forMainNav),
                ),
                default => $this->appendIfNotNull($result, $this->simpleItem($type, $item, $forMainNav)),
            };
        }

        return $result;
    }

    /**
     * Agrupa El tiempo, Bolsa, Pasatiempos, Programación TV, Avisos y Foro bajo «Servicios».
     *
     * @param list<NavMenuItemView> $items
     * @param list<array<string, mixed>> $mainConfig
     *
     * @return list<NavMenuItemView>
     */
    private function groupMainServices(SiteContext $context, array $items, array $mainConfig): array
    {
        $serviceRoutes = [
            'public_weather',
            'public_markets',
            'public_games',
            'public_tv',
            'public_classifieds',
            'public_forum',
        ];

        $utilityPages = [
            'public_local_traffic' => 'Tráfico',
            'public_local_transport' => 'Transporte',
            'public_local_pharmacies' => 'Farmacias',
            'public_local_phones' => 'Teléfonos útiles',
        ];

        $extracted = [];
        $rest = [];
        foreach ($items as $item) {
            if (\in_array($item->route, $serviceRoutes, true)) {
                $extracted[$item->route] = $item;
            } else {
                $rest[] = $item;
            }
        }

        $servicesEnabled = true;
        $servicesLabel = 'Servicios';
        foreach ($mainConfig as $row) {
            if (($row['type'] ?? '') === NavMenuItemType::Services->value) {
                $servicesEnabled = (bool) ($row['enabled'] ?? true);
                $custom = trim((string) ($row['label'] ?? ''));
                if ($custom !== '') {
                    $servicesLabel = $custom;
                }
                break;
            }
        }

        // Si el grupo está activo, completa hijos desde módulos aunque estén desactivados como entradas sueltas.
        if ($servicesEnabled) {
            $weatherConfig = $this->settingsReader->getWeatherConfig($context);
            $marketsConfig = $this->settingsReader->getMarketConfig($context);
            $gamesConfig = $this->settingsReader->getGamesConfig($context);
            $tvConfig = $this->settingsReader->getTvConfig($context);
            $classifiedsConfig = $this->settingsReader->getClassifiedsConfig($context);
            $forumConfig = $this->settingsReader->getForumConfig($context);
            $labelOverrides = $this->serviceLabelOverrides($mainConfig);

            if ($weatherConfig->enabled && $weatherConfig->isConfigured() && !isset($extracted['public_weather'])) {
                $extracted['public_weather'] = $this->weatherItem([
                    'label' => $labelOverrides['weather'] ?? 'El tiempo',
                ], true);
            }
            if ($marketsConfig->enabled && $marketsConfig->isConfigured() && !isset($extracted['public_markets'])) {
                $extracted['public_markets'] = $this->marketsItem([
                    'label' => $labelOverrides['markets'] ?? 'Bolsa',
                ], true);
            }
            if ($gamesConfig->enabled && $gamesConfig->isConfigured() && !isset($extracted['public_games'])) {
                $extracted['public_games'] = $this->gamesItem([
                    'label' => $labelOverrides['games'] ?? 'Pasatiempos',
                ], true);
            }
            if ($tvConfig->enabled && $tvConfig->isConfigured() && !isset($extracted['public_tv'])) {
                $extracted['public_tv'] = $this->tvItem([
                    'label' => $labelOverrides['tv'] ?? 'Programación TV',
                ], true);
            }
            if ($classifiedsConfig->enabled && $classifiedsConfig->isConfigured() && !isset($extracted['public_classifieds'])) {
                $extracted['public_classifieds'] = $this->classifiedsItem([
                    'label' => $labelOverrides['classifieds'] ?? 'Avisos',
                ], true);
            }
            if ($forumConfig->enabled && $forumConfig->isConfigured() && !isset($extracted['public_forum'])) {
                $extracted['public_forum'] = $this->forumItem([
                    'label' => $labelOverrides['forum'] ?? 'Foro',
                ], true);
            }
        }

        $orderedChildren = [];
        foreach ($serviceRoutes as $route) {
            if (isset($extracted[$route])) {
                $orderedChildren[] = $extracted[$route];
            }
        }

        if ($servicesEnabled) {
            foreach ($utilityPages as $route => $defaultLabel) {
                $orderedChildren[] = $this->view(
                    label: $defaultLabel,
                    route: $route,
                );
            }
        }

        if ($orderedChildren === []) {
            return $rest;
        }

        if (!$servicesEnabled) {
            return [...$rest, ...$orderedChildren];
        }

        $activeRoutes = [];
        foreach ($orderedChildren as $child) {
            foreach ($child->activeRoutes as $route) {
                $activeRoutes[] = $route;
            }
        }

        $servicesItem = new NavMenuItemView(
            label: $servicesLabel,
            url: '#',
            route: '',
            activeRoutes: array_values(array_unique($activeRoutes)),
            children: $orderedChildren,
            isDropdown: true,
        );

        return $this->insertServicesItem($rest, $servicesItem);
    }

    /**
     * @param list<array<string, mixed>> $mainConfig
     *
     * @return array<string, string>
     */
    private function serviceLabelOverrides(array $mainConfig): array
    {
        $map = [];
        foreach ($mainConfig as $row) {
            $type = (string) ($row['type'] ?? '');
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            if (\in_array($type, ['weather', 'markets', 'games', 'tv', 'classifieds', 'forum'], true)) {
                $map[$type] = $label;
            }
        }

        return $map;
    }

    /**
     * @param list<NavMenuItemView> $items
     *
     * @return list<NavMenuItemView>
     */
    private function insertServicesItem(array $items, NavMenuItemView $servicesItem): array
    {
        $result = [];
        $inserted = false;
        foreach ($items as $item) {
            if (!$inserted && $item->route === 'public_horoscope') {
                $result[] = $servicesItem;
                $inserted = true;
            }
            $result[] = $item;
        }
        if (!$inserted) {
            // Tras la última categoría o edición; si no, al final.
            $result = [];
            $lastEditorialIndex = -1;
            foreach ($items as $index => $item) {
                if (\in_array($item->route, ['home', 'public_edition', 'public_category_show'], true)) {
                    $lastEditorialIndex = $index;
                }
            }
            foreach ($items as $index => $item) {
                $result[] = $item;
                if (!$inserted && $index === $lastEditorialIndex) {
                    $result[] = $servicesItem;
                    $inserted = true;
                }
            }
            if (!$inserted) {
                $result[] = $servicesItem;
            }
        }

        return $result;
    }

    /**
     * @param list<NavMenuItemView> $result
     */
    private function appendIfNotNull(array &$result, ?NavMenuItemView $view): void
    {
        if ($view !== null) {
            $result[] = $view;
        }
    }

    /**
     * @param list<NavMenuItemView> $result
     * @param list<Category> $navCategories
     */
    private function appendCategoryItems(array &$result, array $navCategories, bool $forMainNav): void
    {
        foreach ($navCategories as $category) {
            $item = $this->categoryFromEntity($category, $forMainNav);
            if ($item !== null) {
                $result[] = $item;
            }
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function simpleItem(NavMenuItemType $type, array $item, bool $forMainNav): ?NavMenuItemView
    {
        return match ($type) {
            NavMenuItemType::Home => $this->view(
                label: $this->label($item, 'Portada'),
                route: 'home',
                cssClass: $forMainNav ? '' : '',
            ),
            NavMenuItemType::Edition => $this->view(
                label: $this->label($item, 'Edición'),
                route: 'public_edition',
                activeRoutes: ['public_edition', 'public_edition_date'],
            ),
            NavMenuItemType::Agenda => $this->view(
                label: $this->label($item, 'Agenda'),
                route: 'public_agenda',
                activeRoutes: ['public_agenda', 'public_agenda_date'],
            ),
            NavMenuItemType::Search => $this->view(
                label: $this->label($item, 'Buscar'),
                route: 'public_search',
            ),
            NavMenuItemType::Page => $this->pageItem($item),
            NavMenuItemType::Custom => $this->customItem($item),
            NavMenuItemType::Admin => $this->view(
                label: $this->label($item, 'Admin'),
                route: 'admin_login',
                cssClass: 'nav-admin',
            ),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $item
     */
    private function pageItem(array $item): ?NavMenuItemView
    {
        $slug = (string) ($item['pageSlug'] ?? '');
        if ($slug === '' || !SitePageRegistry::isValidSlug($slug)) {
            return null;
        }

        $page = SitePageRegistry::get($slug);
        $label = $this->label($item, $page['title'] ?? $slug);

        return $this->view(
            label: $label,
            route: 'public_page_show',
            routeSlug: $slug,
            openInNewTab: (bool) ($item['openInNewTab'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function customItem(array $item): ?NavMenuItemView
    {
        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '') {
            return null;
        }

        return new NavMenuItemView(
            label: $this->label($item, 'Enlace'),
            url: $url,
            openInNewTab: (bool) ($item['openInNewTab'] ?? false),
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function loginItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Entrar'),
            route: 'cuenta_login',
            cssClass: $forMainNav ? 'nav-account' : '',
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function registerItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Registro'),
            route: 'cuenta_register',
            cssClass: $forMainNav ? 'nav-account' : '',
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function profileItem(array $item, bool $forMainNav): NavMenuItemView
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        $defaultLabel = 'Mi cuenta';
        if (is_object($user) && method_exists($user, 'getName') && (string) $user->getName() !== '') {
            $defaultLabel = (string) $user->getName();
        }

        return $this->view(
            label: $this->label($item, $defaultLabel),
            route: 'cuenta_profile',
            cssClass: $forMainNav ? 'nav-account nav-account--profile' : '',
            activeRoutes: [
                'cuenta_profile',
                'cuenta_forum_topics',
                'cuenta_forum_posts',
                'cuenta_comments',
                'cuenta_classifieds',
                'cuenta_password',
            ],
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function logoutItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Salir'),
            route: 'logout',
            cssClass: $forMainNav ? 'nav-account' : '',
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function weatherItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'El tiempo'),
            route: 'public_weather',
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function marketsItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Bolsa'),
            route: 'public_markets',
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function tvItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Programación TV'),
            route: 'public_tv',
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function gamesItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Pasatiempos'),
            route: 'public_games',
            activeRoutes: ['public_games', 'public_games_sudoku', 'public_games_anagrama'],
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function horoscopeItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Horóscopo'),
            route: 'public_horoscope',
            activeRoutes: ['public_horoscope', 'public_horoscope_sign'],
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function classifiedsItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Avisos'),
            route: 'public_classifieds',
            activeRoutes: ['public_classifieds', 'public_classifieds_category', 'public_classifieds_show', 'public_classifieds_new'],
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function forumItem(array $item, bool $forMainNav): NavMenuItemView
    {
        return $this->view(
            label: $this->label($item, 'Foro'),
            route: 'public_forum',
            activeRoutes: ['public_forum', 'public_forum_category', 'public_forum_show', 'public_forum_new'],
        );
    }

    /**
     * @param array<string, mixed> $item
     * @param list<Category> $allCategories
     */
    private function categoryItem(array $item, array $allCategories, bool $forMainNav): ?NavMenuItemView
    {
        $categoryId = (int) ($item['categoryId'] ?? 0);
        foreach ($allCategories as $category) {
            if ($category->getId() === $categoryId) {
                return $this->categoryFromEntity($category, $forMainNav, $this->label($item, $category->getName()));
            }
        }

        return null;
    }

    private function categoryFromEntity(Category $category, bool $forMainNav, ?string $labelOverride = null): ?NavMenuItemView
    {
        $slug = $category->getSlug();
        $label = $labelOverride ?? $category->getName();
        $children = [];

        if ($forMainNav && $category->getChildren()->count() > 0) {
            $children[] = $this->view(
                label: 'Todas en '.$category->getName(),
                route: 'public_category_show',
                routeSlug: $slug,
            );
            foreach ($category->getChildren() as $child) {
                $children[] = $this->view(
                    label: $child->getName(),
                    route: 'public_category_show',
                    routeSlug: $child->getSlug(),
                );
            }

            return new NavMenuItemView(
                label: $label,
                url: $this->path('public_category_show', ['slug' => $slug]),
                route: 'public_category_show',
                routeSlug: $slug,
                activeRoutes: ['public_category_show'],
                children: $children,
                isDropdown: true,
            );
        }

        return $this->view(
            label: $label,
            route: 'public_category_show',
            routeSlug: $slug,
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function label(array $item, string $default): string
    {
        $label = trim((string) ($item['label'] ?? ''));

        return $label !== '' ? $label : $default;
    }

    /**
     * @param list<string> $activeRoutes
     */
    private function view(
        string $label,
        string $route,
        ?string $routeSlug = null,
        array $activeRoutes = [],
        string $cssClass = '',
        bool $openInNewTab = false,
    ): NavMenuItemView {
        $params = $routeSlug !== null ? ['slug' => $routeSlug] : [];

        return new NavMenuItemView(
            label: $label,
            url: $this->path($route, $params),
            route: $route,
            routeSlug: $routeSlug,
            activeRoutes: $activeRoutes !== [] ? $activeRoutes : [$route],
            cssClass: $cssClass,
            openInNewTab: $openInNewTab,
        );
    }

    /** @param array<string, string> $params */
    private function path(string $route, array $params = []): string
    {
        return $this->urlGenerator->generate($route, $params);
    }

    /** @return list<Category> */
    private function loadNavCategories(SiteContext $context): array
    {
        $em = $this->siteEmFactory->forContext($context);
        $repo = $em->getRepository(Category::class);
        if (!$repo instanceof CategoryRepository) {
            return [];
        }

        return $repo->findNavRoots();
    }

    /** @return list<Category> */
    private function loadAllCategories(SiteContext $context): array
    {
        $em = $this->siteEmFactory->forContext($context);
        $repo = $em->getRepository(Category::class);
        if (!$repo instanceof CategoryRepository) {
            return [];
        }

        return $repo->findRootsForAdmin();
    }
}
