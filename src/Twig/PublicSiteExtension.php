<?php

namespace App\Twig;

use App\Core\Site\Dto\SiteFooterConfig;
use App\Core\Site\Dto\SiteSeoConfig;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Site\Enum\SiteLinkGroup;
use App\Entity\Site\SiteLink;
use App\Repository\Site\SiteLinkRepository;
use App\Weather\WeatherCodeMapper;
use App\Weather\WeatherService;
use App\Markets\MarketFormatter;
use App\Markets\MarketService;
use App\Tv\TvFormatter;
use App\Tv\TvChannelRegistry;
use App\Tv\TvProgrammingService;
use App\Horoscope\HoroscopeAiService;
use App\Core\Site\Dto\NavMenuItemView;
use App\Editorial\ArticleFeaturedImageGuard;
use App\Editorial\SeoImageAttributes;
use App\Entity\Site\Article;
use App\Entity\Site\ArticleImage;
use App\Navigation\NavMenuProvider;
use App\Navigation\TopBarMediaSlidesBuilder;
use App\Seo\PublicUrlFactory;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class PublicSiteExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SiteContextHolder $siteHolder,
        private readonly SiteSettingsReader $settingsReader,
        private readonly WeatherService $weatherService,
        private readonly MarketService $marketService,
        private readonly TvProgrammingService $tvProgrammingService,
        private readonly HoroscopeAiService $horoscopeAiService,
        private readonly NavMenuProvider $navMenuProvider,
        private readonly TopBarMediaSlidesBuilder $topBarMediaSlidesBuilder,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly RequestStack $requestStack,
        private readonly ArticleFeaturedImageGuard $featuredImageGuard,
        private readonly SeoImageAttributes $seoImageAttributes,
        private readonly PublicUrlFactory $publicUrls,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('weather_icon_class', WeatherCodeMapper::iconClass(...)),
            new TwigFunction('weather_description', WeatherCodeMapper::describe(...)),
            new TwigFunction('market_format_price', MarketFormatter::formatPrice(...)),
            new TwigFunction('market_format_change', MarketFormatter::formatChange(...)),
            new TwigFunction('market_format_change_percent', MarketFormatter::formatChangePercent(...)),
            new TwigFunction('market_change_class', MarketFormatter::changeClass(...)),
            new TwigFunction('market_sparkline_path', MarketFormatter::sparklinePath(...)),
            new TwigFunction('market_region_label', MarketFormatter::regionLabel(...)),
            new TwigFunction('market_sparkline_trend', MarketFormatter::sparklineTrendLabel(...)),
            new TwigFunction('tv_format_time', TvFormatter::formatTime(...)),
            new TwigFunction('tv_format_time_range', TvFormatter::formatTimeRange(...)),
            new TwigFunction('tv_format_duration', TvFormatter::formatDuration(...)),
            new TwigFunction('tv_group_label', TvChannelRegistry::groupLabel(...)),
            new TwigFunction('nav_item_active', $this->isNavItemActive(...)),
            new TwigFunction('article_usable_featured', $this->usableFeaturedImage(...)),
            new TwigFunction('article_is_visible', $this->articleIsVisible(...)),
            new TwigFunction('seo_article_image', $this->seoArticleImage(...), ['is_safe' => ['html']]),
            new TwigFunction('seo_img', $this->seoImg(...), ['is_safe' => ['html']]),
            new TwigFunction('seo_canonical_url', $this->canonicalUrl(...)),
        ];
    }

    public function getFilters(): array
    {
        return [
            new \Twig\TwigFilter('seo_enrich_html', $this->seoEnrichHtml(...), ['is_safe' => ['html']]),
        ];
    }

    public function usableFeaturedImage(Article $article): ?ArticleImage
    {
        return $this->featuredImageGuard->getUsableFeaturedImage($article);
    }

    public function articleIsVisible(Article $article): bool
    {
        return $this->featuredImageGuard->isPubliclyVisible($article);
    }

    public function canonicalUrl(?string $explicit = null): string
    {
        if (\is_string($explicit) && trim($explicit) !== '') {
            return $this->publicUrls->normalize($explicit);
        }

        $request = $this->requestStack->getCurrentRequest();
        $path = $request?->getPathInfo() ?? '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $this->publicUrls->absolutePath($path);
    }

    /**
     * @param array{class?: string, loading?: string, fetchpriority?: string} $options
     */
    public function seoArticleImage(?ArticleImage $image, string $fallbackTitle, array $options = []): string
    {
        if ($image === null || trim($image->getUrl()) === '') {
            return '';
        }

        $attrs = $this->seoImageAttributes->forArticleImage($image, $fallbackTitle);

        return $this->buildImgTag(
            $image->getUrl(),
            $attrs['alt'],
            $attrs['title'],
            $attrs['width'],
            $attrs['height'],
            $options,
        );
    }

    /**
     * @param array{class?: string, loading?: string, fetchpriority?: string, title?: string, width?: int|null, height?: int|null} $options
     */
    public function seoImg(string $src, string $alt, array $options = []): string
    {
        $src = trim($src);
        if ($src === '') {
            return '';
        }

        $altText = trim($alt) !== '' ? trim($alt) : 'Imagen';
        $title = trim((string) ($options['title'] ?? ''));
        if ($title === '') {
            $title = $altText;
        }

        return $this->buildImgTag(
            $src,
            $altText,
            $title,
            isset($options['width']) ? (int) $options['width'] : null,
            isset($options['height']) ? (int) $options['height'] : null,
            $options,
        );
    }

    public function seoEnrichHtml(string $html, string $fallbackTitle = ''): string
    {
        return $this->seoImageAttributes->enrichHtml($html, $fallbackTitle);
    }

    /**
     * @param array{class?: string, loading?: string, fetchpriority?: string} $options
     */
    private function buildImgTag(
        string $src,
        string $alt,
        string $title,
        ?int $width,
        ?int $height,
        array $options,
    ): string {
        $loading = (string) ($options['loading'] ?? 'lazy');
        if (!\in_array($loading, ['lazy', 'eager', 'auto'], true)) {
            $loading = 'lazy';
        }

        $parts = [
            'src="'. htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"',
            'alt="'.htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"',
            'title="'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"',
            'loading="'.$loading.'"',
            'decoding="async"',
        ];

        if ($width !== null && $width > 0) {
            $parts[] = 'width="'.$width.'"';
        }
        if ($height !== null && $height > 0) {
            $parts[] = 'height="'.$height.'"';
        }

        $class = trim((string) ($options['class'] ?? ''));
        if ($class !== '') {
            $parts[] = 'class="'.htmlspecialchars($class, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
        }

        $fetchpriority = trim((string) ($options['fetchpriority'] ?? ''));
        if (\in_array($fetchpriority, ['high', 'low', 'auto'], true)) {
            $parts[] = 'fetchpriority="'.$fetchpriority.'"';
        }

        return '<img '.implode(' ', $parts).'>';
    }

    public function getGlobals(): array
    {
        $site = $this->siteHolder->get();
        if ($site === null) {
            return $this->emptyGlobals();
        }

        try {
            return $this->globalsForSite($site);
        } catch (\Throwable) {
            return $this->emptyGlobals();
        }
    }

    /** @return array<string, mixed> */
    private function emptyGlobals(): array
    {
        return [
            'footer' => new SiteFooterConfig(),
            'accommodations' => new \App\Core\Site\Dto\AccommodationsConfig(),
            'weatherNav' => ['enabled' => false, 'label' => '', 'temp' => null, 'sponsor' => null, 'sponsorUrl' => null],
            'marketsNav' => ['enabled' => false, 'label' => '', 'changePercent' => null],
            'tvNav' => ['enabled' => false, 'label' => '', 'programme' => null],
            'gamesNav' => ['enabled' => false, 'label' => 'Pasatiempos'],
            'horoscopeNav' => ['enabled' => false, 'label' => 'Horóscopo', 'headline' => null],
            'classifiedsNav' => ['enabled' => false, 'label' => 'Avisos'],
            'forumNav' => ['enabled' => false, 'label' => 'Foro'],
            'analytics' => new \App\Core\Site\Dto\AnalyticsConfig(),
            'siteSeo' => new SiteSeoConfig(),
            'mainNav' => [],
            'footerNavSections' => [],
            'footerNavLegal' => [],
            'siteLinks' => $this->emptySiteLinks(),
            'topBarMediaSlides' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function globalsForSite(\App\Core\Site\SiteContext $site): array
    {
        $weatherConfig = $this->settingsReader->getWeatherConfig($site);
        $weatherNav = ['enabled' => false, 'label' => '', 'temp' => null, 'sponsor' => null, 'sponsorUrl' => null];
        if ($weatherConfig->enabled && $weatherConfig->isConfigured()) {
            $forecast = $this->weatherService->getForecast($site);
            $sponsor = $weatherConfig->sponsor;
            $weatherNav = [
                'enabled' => true,
                'label' => $weatherConfig->placeName !== '' ? $weatherConfig->placeName : $weatherConfig->postalCode,
                'temp' => $forecast?->currentTemp,
                'sponsor' => $sponsor->showInNav && $sponsor->name !== '' ? $sponsor->name : null,
                'sponsorUrl' => $sponsor->showInNav && $sponsor->linkUrl !== '' ? $sponsor->linkUrl : null,
            ];
        }

        $marketsConfig = $this->settingsReader->getMarketConfig($site);
        $tvConfig = $this->settingsReader->getTvConfig($site);
        $gamesConfig = $this->settingsReader->getGamesConfig($site);
        $horoscopeConfig = $this->settingsReader->getHoroscopeConfig($site);
        $classifiedsConfig = $this->settingsReader->getClassifiedsConfig($site);
        $forumConfig = $this->settingsReader->getForumConfig($site);
        $marketsNav = ['enabled' => false, 'label' => 'IBEX 35', 'changePercent' => null];
        if ($marketsConfig->enabled && $marketsConfig->isConfigured()) {
            $board = $this->marketService->getBoard($site);
            $ibex = null;
            if ($board !== null) {
                foreach ($board->indices as $snapshot) {
                    if ($snapshot->id === 'ibex35') {
                        $ibex = $snapshot;
                        break;
                    }
                }
                if ($ibex === null && $board->indices !== []) {
                    $ibex = $board->indices[0];
                }
            }
            $marketsNav = [
                'enabled' => true,
                'label' => $ibex?->name ?? 'Bolsa',
                'changePercent' => $ibex?->changePercent,
            ];
        }

        $tvNav = ['enabled' => false, 'label' => 'Guía TV', 'programme' => null];
        if ($tvConfig->enabled && $tvConfig->isConfigured()) {
            $schedule = $this->tvProgrammingService->getSchedule($site);
            $nowItems = $schedule?->nowOnTv() ?? [];
            $first = $nowItems[0] ?? null;
            $tvNav = [
                'enabled' => true,
                'label' => 'Guía TV',
                'programme' => $first !== null ? $first['programme']->title : null,
            ];
        }

        $gamesNav = ['enabled' => false, 'label' => 'Pasatiempos'];
        if ($gamesConfig->enabled && $gamesConfig->isConfigured()) {
            $gamesNav = [
                'enabled' => true,
                'label' => 'Pasatiempos',
            ];
        }

        $horoscopeNav = ['enabled' => false, 'label' => 'Horóscopo', 'headline' => null];
        if ($horoscopeConfig->enabled && $horoscopeConfig->isConfigured()) {
            $board = $this->horoscopeAiService->getBoard($site);
            $headline = $board?->signs[0]?->headline ?? null;
            $horoscopeNav = [
                'enabled' => true,
                'label' => 'Horóscopo',
                'headline' => $headline,
            ];
        }

        $classifiedsNav = ['enabled' => false, 'label' => 'Avisos'];
        if ($classifiedsConfig->enabled && $classifiedsConfig->isConfigured()) {
            $classifiedsNav = [
                'enabled' => true,
                'label' => 'Avisos',
            ];
        }

        $forumNav = ['enabled' => false, 'label' => 'Foro'];
        if ($forumConfig->enabled && $forumConfig->isConfigured()) {
            $forumNav = [
                'enabled' => true,
                'label' => 'Foro',
            ];
        }

        return [
            'footer' => $this->settingsReader->getFooterConfig($site),
            'accommodations' => $this->settingsReader->getAccommodationsConfig($site),
            'weatherNav' => $weatherNav,
            'marketsNav' => $marketsNav,
            'tvNav' => $tvNav,
            'gamesNav' => $gamesNav,
            'horoscopeNav' => $horoscopeNav,
            'classifiedsNav' => $classifiedsNav,
            'forumNav' => $forumNav,
            'analytics' => $this->settingsReader->getAnalyticsConfig($site),
            'siteSeo' => $this->settingsReader->getSeoConfig($site),
            'mainNav' => $this->navMenuProvider->buildMain($site),
            'footerNavSections' => $this->navMenuProvider->buildFooterSections($site),
            'footerNavLegal' => $this->navMenuProvider->buildFooterLegal($site),
            'siteLinks' => $this->loadSiteLinks($site),
            'topBarMediaSlides' => $this->topBarMediaSlidesBuilder->build($site),
        ];
    }

    public function isNavItemActive(NavMenuItemView $item): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return false;
        }

        $route = (string) $request->attributes->get('_route', '');
        if ($route === '' || !in_array($route, $item->activeRoutes, true)) {
            if ($item->children !== []) {
                foreach ($item->children as $child) {
                    if ($this->isNavItemActive($child)) {
                        return true;
                    }
                }
            }

            return false;
        }

        if ($item->routeSlug === null) {
            return true;
        }

        $slug = $request->attributes->get('slug');

        return is_string($slug) && $item->routeSlug === $slug;
    }

    /** @return array{footerOtherMedia: list<SiteLink>, footerInterest: list<SiteLink>, sidebarOtherMedia: list<SiteLink>, sidebarInterest: list<SiteLink>} */
    private function loadSiteLinks(\App\Core\Site\SiteContext $site): array
    {
        try {
            $em = $this->siteEmFactory->forContext($site);
            $repo = $em->getRepository(SiteLink::class);
            if (!$repo instanceof SiteLinkRepository) {
                return $this->emptySiteLinks();
            }

            return [
                'footerOtherMedia' => $repo->findEnabledForFooter(SiteLinkGroup::OtherMedia),
                'footerInterest' => $repo->findEnabledForFooter(SiteLinkGroup::Interest),
                'sidebarOtherMedia' => $repo->findEnabledForSidebar(SiteLinkGroup::OtherMedia),
                'sidebarInterest' => $repo->findEnabledForSidebar(SiteLinkGroup::Interest),
            ];
        } catch (\Throwable) {
            return $this->emptySiteLinks();
        }
    }

    /** @return array{footerOtherMedia: list<SiteLink>, footerInterest: list<SiteLink>, sidebarOtherMedia: list<SiteLink>, sidebarInterest: list<SiteLink>} */
    private function emptySiteLinks(): array
    {
        return [
            'footerOtherMedia' => [],
            'footerInterest' => [],
            'sidebarOtherMedia' => [],
            'sidebarInterest' => [],
        ];
    }
}
