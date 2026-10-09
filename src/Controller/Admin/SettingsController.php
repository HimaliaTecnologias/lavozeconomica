<?php

namespace App\Controller\Admin;

use App\Core\Site\Dto\SiteMailConfig;
use App\Core\Site\SiteConfigTransferService;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use App\Entity\Platform\Site;
use App\Editorial\SiteBrandingUploadService;
use App\Editorial\SiteMailer;
use App\Editorial\SiteFontRegistry;
use App\Editorial\SitePageRegistry;
use App\Editorial\SiteSocialNetworkRegistry;
use App\Ingestion\HimtecMedia\HimtecMediaFetchService;
use App\Security\SiteAuthorizationChecker;
use App\Weather\WeatherAiSummaryService;
use App\Weather\WeatherService;
use App\Markets\MarketIndexRegistry;
use App\Markets\MarketAiSummaryService;
use App\Markets\MarketService;
use App\Tv\TvChannelRegistry;
use App\Tv\TvProgrammingService;
use App\Games\GamesService;
use App\Horoscope\HoroscopeAiService;
use App\Navigation\NavMenuDefaults;
use App\Navigation\NavMenuNormalizer;
use App\Entity\Site\WeatherWebcam;
use App\Repository\Site\WeatherWebcamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/settings')]
#[IsGranted('ROLE_USER')]
final class SettingsController extends AbstractAdminController
{
    /** @var array<int, string> */
    public const INTERVAL_OPTIONS = [
        5 => 'Cada 5 minutos',
        15 => 'Cada 15 minutos',
        30 => 'Cada 30 minutos',
        60 => 'Cada hora',
        360 => 'Cada 6 horas',
        1440 => 'Cada 24 horas',
    ];

    /** @var array<int, string> */
    public const PUBLISH_STAGGER_OPTIONS = [
        0 => 'Sin espera (todas a la vez)',
        1 => 'Cada 1 minuto',
        2 => 'Cada 2 minutos',
        5 => 'Cada 5 minutos',
        10 => 'Cada 10 minutos',
        15 => 'Cada 15 minutos',
        30 => 'Cada 30 minutos',
        60 => 'Cada hora',
    ];

    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly HimtecMediaFetchService $fetchService,
        private readonly SiteBrandingUploadService $brandingUpload,
        private readonly WeatherService $weatherService,
        private readonly WeatherAiSummaryService $weatherAiSummaryService,
        private readonly MarketService $marketService,
        private readonly MarketAiSummaryService $marketAiSummaryService,
        private readonly TvProgrammingService $tvProgrammingService,
        private readonly GamesService $gamesService,
        private readonly HoroscopeAiService $horoscopeAiService,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly SiteMailer $siteMailer,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteConfigTransferService $configTransfer,
        #[Autowire(env: 'string:default::OPENAI_API_KEY')]
        private readonly string $defaultOpenaiApiKey = '',
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_settings_index', methods: ['GET'])]
    public function index(): Response
    {
        [$site] = $this->requireSiteAccess();
        $appearance = $this->settingsReader->getAppearance($site);
        $ingest = $this->settingsReader->getIngestConfig($site);
        $layout = $this->settingsReader->getLayoutConfig($site);
        $footer = $this->settingsReader->getFooterConfig($site);
        $mail = $this->settingsReader->getMailConfig($site);

        return $this->render('admin/settings/index.html.twig', [
            'site' => $site,
            'appearance' => $appearance->toArray(),
            'ingest' => $ingest,
            'layout' => $layout,
            'footer' => $footer,
            'mail' => $mail,
            'ai' => $this->settingsReader->getAiConfig($site),
        ]);
    }

    #[Route('/ai', name: 'admin_settings_ai', methods: ['GET', 'POST'])]
    public function ai(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getAiConfig($site);
        $weather = $this->settingsReader->getWeatherConfig($site);
        $markets = $this->settingsReader->getMarketConfig($site);
        $horoscope = $this->settingsReader->getHoroscopeConfig($site);
        $modelOptions = ['gpt-4o-mini', 'gpt-4o', 'gpt-4.1-mini', 'gpt-4.1'];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_ai', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $openaiApiKey = trim((string) $request->request->get('openaiApiKey', ''));
            $clearApiKey = $request->request->has('clearApiKey');
            $model = trim((string) $request->request->get('model', 'gpt-4o-mini'));
            if (!\in_array($model, $modelOptions, true)) {
                $model = 'gpt-4o-mini';
            }
            $articleImprovePrompt = trim((string) $request->request->get('articleImprovePrompt', ''));
            if ($articleImprovePrompt === '') {
                $articleImprovePrompt = \App\Core\Site\Dto\AiConfig::DEFAULT_ARTICLE_IMPROVE_PROMPT;
            }
            if (mb_strlen($articleImprovePrompt) > 12000) {
                $articleImprovePrompt = mb_substr($articleImprovePrompt, 0, 12000);
            }

            if ($clearApiKey) {
                $openaiApiKey = '';
            } elseif ($openaiApiKey === '' && $config->hasOpenaiApiKey) {
                $openaiApiKey = $config->openaiApiKey;
            }

            $this->settingsWriter->setMany($site, [
                ['ai', 'openai_api_key', $openaiApiKey],
                ['ai', 'model', $model],
                ['ai', 'article_improve_prompt', $articleImprovePrompt],
                // Compatibilidad con módulos que aún leen su propia clave.
                ['weather', 'openai_api_key', $openaiApiKey],
                ['markets', 'openai_api_key', $openaiApiKey],
                ['horoscope', 'openai_api_key', $openaiApiKey],
            ]);
            $this->addFlash('success', 'Configuración de IA guardada.');

            return $this->redirectToRoute('admin_settings_ai');
        }

        return $this->render('admin/settings/ai.html.twig', [
            'site' => $site,
            'config' => $config,
            'data' => [
                'model' => $config->model,
                'articleImprovePrompt' => $config->resolvedArticleImprovePrompt(),
            ],
            'errors' => [],
            'modelOptions' => $modelOptions,
            'weather' => $weather,
            'markets' => $markets,
            'horoscope' => $horoscope,
            'defaultArticleImprovePrompt' => \App\Core\Site\Dto\AiConfig::DEFAULT_ARTICLE_IMPROVE_PROMPT,
        ]);
    }

    #[Route('/layout', name: 'admin_settings_layout', methods: ['GET', 'POST'])]
    public function layout(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $current = $this->settingsReader->getLayoutConfig($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_layout', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $home = (string) $request->request->get('homeTemplate', '');
            $category = (string) $request->request->get('categoryTemplate', '');
            $article = (string) $request->request->get('articleTemplate', '');
            $relatedCount = (int) $request->request->get('relatedArticlesCount', 8);
            $homeCategoryCount = (int) $request->request->get('homeCategoryArticlesCount', 4);
            $sidebarPopularCount = (int) $request->request->get('sidebarPopularCount', 5);
            $sidebarLatestCount = (int) $request->request->get('sidebarLatestCount', 5);

            $errors = [];
            if (\App\Entity\Site\Enum\HomeLayoutTemplate::tryFrom($home) === null) {
                $errors['homeTemplate'] = 'Plantilla de portada no válida.';
            }
            if (\App\Entity\Site\Enum\CategoryLayoutTemplate::tryFrom($category) === null) {
                $errors['categoryTemplate'] = 'Plantilla de categoría no válida.';
            }
            if (\App\Entity\Site\Enum\ArticleLayoutTemplate::tryFrom($article) === null) {
                $errors['articleTemplate'] = 'Plantilla de artículo no válida.';
            }
            if (!\in_array($relatedCount, [4, 6, 8], true)) {
                $errors['relatedArticlesCount'] = 'El número de noticias relacionadas debe ser 4, 6 u 8.';
            }
            if (!\in_array($homeCategoryCount, [2, 3, 4], true)) {
                $errors['homeCategoryArticlesCount'] = 'El número de noticias por sección debe ser 2, 3 o 4.';
            }
            $allowedSidebarCounts = range(
                \App\Core\Site\Dto\SiteLayoutConfig::SIDEBAR_COUNT_MIN,
                \App\Core\Site\Dto\SiteLayoutConfig::SIDEBAR_COUNT_MAX,
            );
            if (!\in_array($sidebarPopularCount, $allowedSidebarCounts, true)) {
                $errors['sidebarPopularCount'] = 'El número de «Más vistas» debe estar entre 3 y 12.';
            }
            if (!\in_array($sidebarLatestCount, $allowedSidebarCounts, true)) {
                $errors['sidebarLatestCount'] = 'El número de «Últimas» debe estar entre 3 y 12.';
            }

            if ($errors === []) {
                $this->settingsWriter->setMany($site, [
                    ['layout', 'home_template', $home],
                    ['layout', 'category_template', $category],
                    ['layout', 'article_template', $article],
                    ['layout', 'related_articles_count', (string) $relatedCount],
                    ['layout', 'home_category_articles_count', (string) $homeCategoryCount],
                    ['layout', 'sidebar_popular_count', (string) $sidebarPopularCount],
                    ['layout', 'sidebar_latest_count', (string) $sidebarLatestCount],
                ]);

                $this->addFlash('success', 'Plantillas de maquetación guardadas.');

                return $this->redirectToRoute('admin_settings_layout');
            }

            return $this->render('admin/settings/layout.html.twig', [
                'site' => $site,
                'data' => [
                    'homeTemplate' => $home,
                    'categoryTemplate' => $category,
                    'articleTemplate' => $article,
                    'relatedArticlesCount' => $relatedCount,
                    'homeCategoryArticlesCount' => $homeCategoryCount,
                    'sidebarPopularCount' => $sidebarPopularCount,
                    'sidebarLatestCount' => $sidebarLatestCount,
                ],
                'errors' => $errors,
                'homeTemplates' => \App\Entity\Site\Enum\HomeLayoutTemplate::cases(),
                'categoryTemplates' => \App\Entity\Site\Enum\CategoryLayoutTemplate::cases(),
                'articleTemplates' => \App\Entity\Site\Enum\ArticleLayoutTemplate::cases(),
                'sidebarCountOptions' => $allowedSidebarCounts,
            ]);
        }

        return $this->render('admin/settings/layout.html.twig', [
            'site' => $site,
            'data' => $current->toArray(),
            'errors' => [],
            'homeTemplates' => \App\Entity\Site\Enum\HomeLayoutTemplate::cases(),
            'categoryTemplates' => \App\Entity\Site\Enum\CategoryLayoutTemplate::cases(),
            'articleTemplates' => \App\Entity\Site\Enum\ArticleLayoutTemplate::cases(),
            'sidebarCountOptions' => range(
                \App\Core\Site\Dto\SiteLayoutConfig::SIDEBAR_COUNT_MIN,
                \App\Core\Site\Dto\SiteLayoutConfig::SIDEBAR_COUNT_MAX,
            ),
        ]);
    }

    #[Route('/content-modules', name: 'admin_settings_content_modules', methods: ['GET', 'POST'])]
    public function contentModules(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getContentModulesConfig($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_content_modules', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $posted = $request->request->all('modules');
            $surfaces = [];
            foreach (\App\ContentModules\ContentModuleSurface::cases() as $surface) {
                $rows = \is_array($posted[$surface->value] ?? null) ? $posted[$surface->value] : [];
                $items = [];
                foreach ($rows as $idx => $row) {
                    if (!\is_array($row)) {
                        continue;
                    }
                    $type = \App\ContentModules\ContentModuleType::tryFrom((string) ($row['type'] ?? ''));
                    if ($type === null || !\in_array($type, $surface->availableTypes(), true)) {
                        continue;
                    }
                    $items[] = \App\Core\Site\Dto\ContentModuleItem::fromArray([
                        'type' => $type->value,
                        'enabled' => isset($row['enabled']),
                        'sortOrder' => (int) ($row['sortOrder'] ?? (($idx + 1) * 10)),
                        'format' => (string) ($row['format'] ?? $type->defaultFormat()->value),
                        'count' => (int) ($row['count'] ?? $type->defaultCount()),
                    ], $type);
                }
                if ($items === []) {
                    $items = \App\Core\Site\Dto\ContentModulesConfig::defaultsForSurface($surface);
                }
                usort($items, static fn ($a, $b) => $a->sortOrder <=> $b->sortOrder);
                $surfaces[$surface->value] = array_map(
                    static fn (\App\Core\Site\Dto\ContentModuleItem $item): array => $item->toArray(),
                    $items,
                );
            }

            $this->settingsWriter->set(
                $site,
                'content_modules',
                'surfaces',
                json_encode($surfaces, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            );

            $this->addFlash('success', 'Módulos de contenido guardados.');

            return $this->redirectToRoute('admin_settings_content_modules');
        }

        return $this->render('admin/settings/content_modules.html.twig', [
            'site' => $site,
            'config' => $config,
            'surfaces' => \App\ContentModules\ContentModuleSurface::cases(),
        ]);
    }

    #[Route('/appearance', name: 'admin_settings_appearance', methods: ['GET', 'POST'])]
    public function appearance(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $current = $this->settingsReader->getAppearance($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_appearance', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $data = [
                'tagline' => trim((string) $request->request->get('tagline', '')),
                'primaryColor' => (string) $request->request->get('primaryColor', '#b91c1c'),
                'secondaryColor' => (string) $request->request->get('secondaryColor', '#7f1d1d'),
                'backgroundColor' => (string) $request->request->get('backgroundColor', '#ffffff'),
                'surfaceColor' => (string) $request->request->get('surfaceColor', '#f4f4f4'),
                'textColor' => (string) $request->request->get('textColor', '#111111'),
                'textMutedColor' => (string) $request->request->get('textMutedColor', '#666666'),
                'headingColor' => (string) $request->request->get('headingColor', '#111111'),
                'linkColor' => trim((string) $request->request->get('linkColor', '')),
                'borderColor' => (string) $request->request->get('borderColor', '#d8d8d8'),
                'topBarColor' => (string) $request->request->get('topBarColor', '#1a1a1a'),
                'navColor' => trim((string) $request->request->get('navColor', '')),
                'navBarColor' => (string) $request->request->get('navBarColor', '#ffffff'),
                'footerColor' => (string) $request->request->get('footerColor', '#12151c'),
                'headingFont' => (string) $request->request->get('headingFont', SiteFontRegistry::DEFAULT_HEADING_FONT),
                'bodyFont' => (string) $request->request->get('bodyFont', SiteFontRegistry::DEFAULT_BODY_FONT),
                'articleFont' => (string) $request->request->get('articleFont', SiteFontRegistry::DEFAULT_ARTICLE_FONT),
                'logoUrl' => $current->logoUrl,
                'logoDarkUrl' => $current->logoDarkUrl,
                'logoMarkUrl' => $current->logoMarkUrl,
                'faviconUrl' => $current->faviconUrl,
            ];

            if ($request->request->getBoolean('removeLogo')) {
                $data['logoUrl'] = '';
            }
            if ($request->request->getBoolean('removeLogoDark')) {
                $data['logoDarkUrl'] = '';
            }
            if ($request->request->getBoolean('removeLogoMark')) {
                $data['logoMarkUrl'] = '';
            }
            if ($request->request->getBoolean('removeFavicon')) {
                $data['faviconUrl'] = '';
            }

            $errors = $this->validateAppearanceColors($data);

            if (!SiteFontRegistry::isValidForRole($data['headingFont'], 'heading')) {
                $errors['headingFont'] = 'Fuente de titulares no válida.';
            }
            if (!SiteFontRegistry::isValidForRole($data['bodyFont'], 'body')) {
                $errors['bodyFont'] = 'Fuente de interfaz no válida.';
            }
            if (!SiteFontRegistry::isValidForRole($data['articleFont'], 'article')) {
                $errors['articleFont'] = 'Fuente de artículos no válida.';
            }

            foreach (['logoFile' => 'logo', 'logoDarkFile' => 'logo_dark', 'logoMarkFile' => 'logo_mark', 'faviconFile' => 'favicon'] as $field => $kind) {
                $file = $request->files->get($field);
                if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    try {
                        $publicPath = $this->brandingUpload->upload($site, $file, $kind);
                        match ($kind) {
                            'logo' => $data['logoUrl'] = $publicPath,
                            'logo_dark' => $data['logoDarkUrl'] = $publicPath,
                            'logo_mark' => $data['logoMarkUrl'] = $publicPath,
                            'favicon' => $data['faviconUrl'] = $publicPath,
                            default => null,
                        };
                    } catch (\InvalidArgumentException $e) {
                        $errors[$field] = $e->getMessage();
                    }
                }
            }

            if ($errors === []) {
                if ($data['linkColor'] === $data['primaryColor']) {
                    $data['linkColor'] = '';
                }
                if ($data['navColor'] === $data['primaryColor']) {
                    $data['navColor'] = '';
                }

                $this->settingsWriter->setMany($site, [
                    ['theme', 'primary_color', $data['primaryColor']],
                    ['theme', 'secondary_color', $data['secondaryColor']],
                    ['theme', 'background_color', $data['backgroundColor']],
                    ['theme', 'surface_color', $data['surfaceColor']],
                    ['theme', 'text_color', $data['textColor']],
                    ['theme', 'text_muted_color', $data['textMutedColor']],
                    ['theme', 'heading_color', $data['headingColor']],
                    ['theme', 'link_color', $data['linkColor']],
                    ['theme', 'border_color', $data['borderColor']],
                    ['theme', 'top_bar_color', $data['topBarColor']],
                    ['theme', 'nav_color', $data['navColor']],
                    ['theme', 'nav_bar_color', $data['navBarColor']],
                    ['theme', 'footer_color', $data['footerColor']],
                    ['theme', 'heading_font', $data['headingFont']],
                    ['theme', 'body_font', $data['bodyFont']],
                    ['theme', 'article_font', $data['articleFont']],
                    ['branding', 'logo_url', $data['logoUrl']],
                    ['branding', 'logo_dark_url', $data['logoDarkUrl']],
                    ['branding', 'logo_mark_url', $data['logoMarkUrl']],
                    ['branding', 'favicon_url', $data['faviconUrl']],
                    ['identity', 'tagline', $data['tagline']],
                ]);

                $this->addFlash('success', 'Apariencia guardada.');

                return $this->redirectToRoute('admin_settings_appearance');
            }

            return $this->render('admin/settings/appearance.html.twig', [
                'site' => $site,
                'data' => $data,
                'errors' => $errors,
                'fonts' => SiteFontRegistry::fonts(),
                'fontCategories' => SiteFontRegistry::CATEGORY_LABELS,
                'headingFonts' => SiteFontRegistry::fontsGroupedForRole('heading'),
                'bodyFonts' => SiteFontRegistry::fontsGroupedForRole('body'),
                'articleFonts' => SiteFontRegistry::fontsGroupedForRole('article'),
            ]);
        }

        return $this->render('admin/settings/appearance.html.twig', [
            'site' => $site,
            'data' => $current->toArray(),
            'errors' => [],
            'fonts' => SiteFontRegistry::fonts(),
            'fontCategories' => SiteFontRegistry::CATEGORY_LABELS,
            'headingFonts' => SiteFontRegistry::fontsGroupedForRole('heading'),
            'bodyFonts' => SiteFontRegistry::fontsGroupedForRole('body'),
            'articleFonts' => SiteFontRegistry::fontsGroupedForRole('article'),
        ]);
    }

    #[Route('/seo', name: 'admin_settings_seo', methods: ['GET', 'POST'])]
    public function seo(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getSeoConfig($site);
        $appearance = $this->settingsReader->getAppearance($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_seo', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $data = [
                'homeTitle' => trim((string) $request->request->get('homeTitle', '')),
                'metaDescription' => trim((string) $request->request->get('metaDescription', '')),
                'metaKeywords' => $this->normalizeMetaKeywords((string) $request->request->get('metaKeywords', '')),
                'titleSeparator' => trim((string) $request->request->get('titleSeparator', ' — ')),
                'defaultRobots' => (string) $request->request->get('defaultRobots', 'index, follow'),
                'defaultOgImage' => trim((string) $request->request->get('defaultOgImage', '')),
                'themeColor' => trim((string) $request->request->get('themeColor', '')),
                'ogSiteName' => trim((string) $request->request->get('ogSiteName', '')),
                'ogLocale' => trim((string) $request->request->get('ogLocale', 'es_ES')),
                'twitterSite' => trim((string) $request->request->get('twitterSite', '')),
                'facebookAppId' => trim((string) $request->request->get('facebookAppId', '')),
                'facebookAdmins' => trim((string) $request->request->get('facebookAdmins', '')),
                'googleSiteVerification' => trim((string) $request->request->get('googleSiteVerification', '')),
                'bingSiteVerification' => trim((string) $request->request->get('bingSiteVerification', '')),
                'facebookDomainVerification' => trim((string) $request->request->get('facebookDomainVerification', '')),
                'pinterestSiteVerification' => trim((string) $request->request->get('pinterestSiteVerification', '')),
                'yandexVerification' => trim((string) $request->request->get('yandexVerification', '')),
                'organizationName' => trim((string) $request->request->get('organizationName', '')),
                'organizationLegalName' => trim((string) $request->request->get('organizationLegalName', '')),
                'organizationType' => (string) $request->request->get('organizationType', 'NewsMediaOrganization'),
                'organizationLogoUrl' => trim((string) $request->request->get('organizationLogoUrl', '')),
                'organizationEmail' => trim((string) $request->request->get('organizationEmail', '')),
                'organizationPhone' => trim((string) $request->request->get('organizationPhone', '')),
                'organizationAddress' => trim((string) $request->request->get('organizationAddress', '')),
                'foundingDate' => trim((string) $request->request->get('foundingDate', '')),
                'defaultAuthor' => trim((string) $request->request->get('defaultAuthor', '')),
                'enableOrganizationSchema' => $request->request->has('enableOrganizationSchema'),
                'enableWebsiteSchema' => $request->request->has('enableWebsiteSchema'),
                'enableNewsArticleSchema' => $request->request->has('enableNewsArticleSchema'),
                'enableRssLinks' => $request->request->has('enableRssLinks'),
                'robotsExtra' => trim((string) $request->request->get('robotsExtra', '')),
                'extraHeadHtml' => trim((string) $request->request->get('extraHeadHtml', '')),
            ];

            if ($data['metaDescription'] === '') {
                $data['metaDescription'] = $appearance->tagline;
            }
            if ($data['titleSeparator'] === '') {
                $data['titleSeparator'] = ' — ';
            }
            if ($data['ogLocale'] === '') {
                $data['ogLocale'] = 'es_ES';
            }

            $errors = $this->validateSeoSettings($data);

            if ($errors === []) {
                $this->settingsWriter->setMany($site, [
                    ['seo', 'home_title', $data['homeTitle']],
                    ['seo', 'meta_description', $data['metaDescription']],
                    ['seo', 'meta_keywords', $data['metaKeywords']],
                    ['seo', 'title_separator', $data['titleSeparator']],
                    ['seo', 'default_robots', $data['defaultRobots']],
                    ['seo', 'default_og_image', $data['defaultOgImage']],
                    ['seo', 'theme_color', $data['themeColor']],
                    ['seo', 'og_site_name', $data['ogSiteName']],
                    ['seo', 'og_locale', $data['ogLocale']],
                    ['seo', 'twitter_site', $data['twitterSite']],
                    ['seo', 'facebook_app_id', $data['facebookAppId']],
                    ['seo', 'facebook_admins', $data['facebookAdmins']],
                    ['seo', 'google_site_verification', $data['googleSiteVerification']],
                    ['seo', 'bing_site_verification', $data['bingSiteVerification']],
                    ['seo', 'facebook_domain_verification', $data['facebookDomainVerification']],
                    ['seo', 'pinterest_site_verification', $data['pinterestSiteVerification']],
                    ['seo', 'yandex_verification', $data['yandexVerification']],
                    ['seo', 'organization_name', $data['organizationName']],
                    ['seo', 'organization_legal_name', $data['organizationLegalName']],
                    ['seo', 'organization_type', $data['organizationType']],
                    ['seo', 'organization_logo_url', $data['organizationLogoUrl']],
                    ['seo', 'organization_email', $data['organizationEmail']],
                    ['seo', 'organization_phone', $data['organizationPhone']],
                    ['seo', 'organization_address', $data['organizationAddress']],
                    ['seo', 'founding_date', $data['foundingDate']],
                    ['seo', 'default_author', $data['defaultAuthor']],
                    ['seo', 'enable_organization_schema', $data['enableOrganizationSchema']],
                    ['seo', 'enable_website_schema', $data['enableWebsiteSchema']],
                    ['seo', 'enable_news_article_schema', $data['enableNewsArticleSchema']],
                    ['seo', 'enable_rss_links', $data['enableRssLinks']],
                    ['seo', 'robots_extra', $data['robotsExtra']],
                    ['seo', 'extra_head_html', $data['extraHeadHtml']],
                ]);

                $this->addFlash('success', 'Configuración SEO guardada.');

                return $this->redirectToRoute('admin_settings_seo');
            }

            return $this->render('admin/settings/seo.html.twig', [
                'site' => $site,
                'data' => $data,
                'errors' => $errors,
                'organizationTypes' => \App\Core\Site\Dto\SiteSeoConfig::ORGANIZATION_TYPES,
                'robotsPresets' => \App\Core\Site\Dto\SiteSeoConfig::ROBOTS_PRESETS,
                'rssUrl' => $this->generateUrl('public_rss', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }

        return $this->render('admin/settings/seo.html.twig', [
            'site' => $site,
            'data' => $config->toArray(),
            'errors' => [],
            'organizationTypes' => \App\Core\Site\Dto\SiteSeoConfig::ORGANIZATION_TYPES,
            'robotsPresets' => \App\Core\Site\Dto\SiteSeoConfig::ROBOTS_PRESETS,
            'rssUrl' => $this->generateUrl('public_rss', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/ingest', name: 'admin_settings_ingest', methods: ['GET', 'POST'])]
    public function ingest(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getIngestConfig($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_ingest', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $jsonUrl = trim((string) $request->request->get('jsonUrl', ''));
            $apiKey = trim((string) $request->request->get('apiKey', ''));
            $intervalMinutes = (int) $request->request->get('intervalMinutes', 60);
            $publishStaggerMinutes = (int) $request->request->get('publishStaggerMinutes', 0);

            if ($apiKey === '' && $config->hasApiKey()) {
                $apiKey = $config->apiKey;
            }

            $errors = $this->validateIngest($enabled, $jsonUrl, $apiKey, $intervalMinutes, $publishStaggerMinutes);

            if ($errors === []) {
                $entries = [
                    ['ingest', 'himtecmedia_enabled', $enabled],
                    ['ingest', 'himtecmedia_json_url', $jsonUrl],
                    ['ingest', 'himtecmedia_interval_minutes', $intervalMinutes],
                    ['ingest', 'himtecmedia_publish_stagger_minutes', $publishStaggerMinutes],
                ];
                if (trim((string) $request->request->get('apiKey', '')) !== '') {
                    $entries[] = ['ingest', 'himtecmedia_api_key', $apiKey];
                }
                $this->settingsWriter->setMany($site, $entries);

                $this->addFlash('success', 'Configuración de HimtecMedia guardada.');

                return $this->redirectToRoute('admin_settings_ingest');
            }

            return $this->render('admin/settings/ingest.html.twig', [
                'site' => $site,
                'config' => $config,
                'formData' => [
                    'enabled' => $enabled,
                    'jsonUrl' => $jsonUrl,
                    'apiKey' => '',
                    'intervalMinutes' => $intervalMinutes,
                    'publishStaggerMinutes' => $publishStaggerMinutes,
                ],
                'errors' => $errors,
                'intervalOptions' => self::INTERVAL_OPTIONS,
                'staggerOptions' => self::PUBLISH_STAGGER_OPTIONS,
            ]);
        }

        return $this->render('admin/settings/ingest.html.twig', [
            'site' => $site,
            'config' => $config,
            'formData' => null,
            'errors' => [],
            'intervalOptions' => self::INTERVAL_OPTIONS,
            'staggerOptions' => self::PUBLISH_STAGGER_OPTIONS,
        ]);
    }

    #[Route('/ingest/fetch-now', name: 'admin_settings_ingest_fetch', methods: ['POST'])]
    public function fetchNow(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();

        if (!$this->isCsrfTokenValid('settings_ingest_fetch', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        try {
            $result = $this->fetchService->fetch($site, null, 'manual');
            if ($result->isSuccessful()) {
                $this->addFlash('success', 'Ingesta completada: '.$result->summary());
            } else {
                $this->addFlash('error', $result->errorMessages[0] ?? $result->summary());
            }

            if ($result->runId !== null) {
                return $this->redirectToRoute('admin_ingest_run_show', ['id' => $result->runId]);
            }
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_settings_ingest');
    }

    #[Route('/footer', name: 'admin_settings_footer', methods: ['GET', 'POST'])]
    public function footer(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $current = $this->settingsReader->getFooterConfig($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_footer', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $data = [
                'contactEmail' => trim((string) $request->request->get('contactEmail', '')),
                'contactPhone' => trim((string) $request->request->get('contactPhone', '')),
                'contactAddress' => trim((string) $request->request->get('contactAddress', '')),
            ];
            foreach (SiteSocialNetworkRegistry::networks() as $network) {
                $field = $network['key'].'Url';
                $data[$field] = trim((string) $request->request->get($field, ''));
            }

            $errors = $this->validateFooter($data);

            if ($errors === []) {
                $settings = [
                    ['footer', 'contact_email', $data['contactEmail']],
                    ['footer', 'contact_phone', $data['contactPhone']],
                    ['footer', 'contact_address', $data['contactAddress']],
                ];
                foreach (SiteSocialNetworkRegistry::networks() as $network) {
                    $settings[] = ['footer', $network['settingKey'], $data[$network['key'].'Url']];
                }

                $this->settingsWriter->setMany($site, $settings);

                $this->addFlash('success', 'Datos del pie de página guardados.');

                return $this->redirectToRoute('admin_settings_footer');
            }

            return $this->render('admin/settings/footer.html.twig', [
                'site' => $site,
                'data' => $data,
                'errors' => $errors,
                'socialNetworks' => SiteSocialNetworkRegistry::networks(),
            ]);
        }

        return $this->render('admin/settings/footer.html.twig', [
            'site' => $site,
            'data' => $current->toArray(),
            'errors' => [],
            'socialNetworks' => SiteSocialNetworkRegistry::networks(),
        ]);
    }

    #[Route('/mail', name: 'admin_settings_mail', methods: ['GET', 'POST'])]
    public function mail(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $current = $this->settingsReader->getMailConfig($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_mail', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $data = [
                'enabled' => $request->request->has('enabled'),
                'host' => trim((string) $request->request->get('host', 'smtp.gmail.com')),
                'port' => (int) $request->request->get('port', 587),
                'encryption' => (string) $request->request->get('encryption', 'tls'),
                'username' => trim((string) $request->request->get('username', '')),
                'password' => (string) $request->request->get('password', ''),
                'fromEmail' => trim((string) $request->request->get('fromEmail', '')),
                'fromName' => trim((string) $request->request->get('fromName', '')),
                'contactRecipient' => trim((string) $request->request->get('contactRecipient', '')),
            ];

            if ($data['password'] === '' && $current->hasPassword) {
                $data['password'] = $current->password;
            } else {
                $data['password'] = SiteMailConfig::normalizeSmtpPassword($data['password']);
            }

            $errors = $this->validateMail($data);

            if ($errors === []) {
                $this->settingsWriter->setMany($site, [
                    ['mail', 'smtp_enabled', $data['enabled']],
                    ['mail', 'smtp_host', $data['host']],
                    ['mail', 'smtp_port', (string) max(1, $data['port'])],
                    ['mail', 'smtp_encryption', $data['encryption']],
                    ['mail', 'smtp_username', $data['username']],
                    ['mail', 'smtp_password', $data['password']],
                    ['mail', 'from_email', $data['fromEmail']],
                    ['mail', 'from_name', $data['fromName']],
                    ['mail', 'contact_recipient', $data['contactRecipient']],
                ]);

                $this->addFlash('success', 'Configuración de correo guardada.');

                return $this->redirectToRoute('admin_settings_mail');
            }

            return $this->render('admin/settings/mail.html.twig', [
                'site' => $site,
                'data' => $data,
                'errors' => $errors,
                'hasPassword' => $current->hasPassword || $data['password'] !== '',
                'gmailNeedsAppPassword' => SiteMailConfig::isGmailHost($data['host'])
                    && $data['password'] !== ''
                    && !SiteMailConfig::isGmailAppPassword($data['password']),
            ]);
        }

        return $this->render('admin/settings/mail.html.twig', [
            'site' => $site,
            'data' => $current->toArray(),
            'errors' => [],
            'hasPassword' => $current->hasPassword,
            'gmailNeedsAppPassword' => $current->needsGmailAppPassword(),
        ]);
    }

    #[Route('/mail/test', name: 'admin_settings_mail_test', methods: ['POST'])]
    public function mailTest(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();

        if (!$this->isCsrfTokenValid('settings_mail_test', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $testRecipient = trim((string) $request->request->get('testRecipient', ''));
        if ($testRecipient === '' || !filter_var($testRecipient, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Indica un email válido para la prueba.');

            return $this->redirectToRoute('admin_settings_mail');
        }

        try {
            $this->siteMailer->sendTestMessage($site, $testRecipient);
            $this->addFlash('success', 'Email de prueba enviado a '.$testRecipient);
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->formatMailTestError($e));
        }

        return $this->redirectToRoute('admin_settings_mail');
    }

    #[Route('/analytics', name: 'admin_settings_analytics', methods: ['GET', 'POST'])]
    public function analytics(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getAnalyticsConfig($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_analytics', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $headCode = trim((string) $request->request->get('headCode', ''));
            $bodyCode = trim((string) $request->request->get('bodyCode', ''));

            $errors = [];
            if ($enabled && $headCode === '' && $bodyCode === '') {
                $errors['tracking'] = 'Indica al menos un bloque de código (cabecera o pie del documento).';
            }

            if ($errors === []) {
                $this->settingsWriter->setMany($site, [
                    ['analytics', 'enabled', $enabled],
                    ['analytics', 'head_code', $headCode],
                    ['analytics', 'body_code', $bodyCode],
                ]);

                $this->addFlash('success', 'Configuración de estadísticas guardada.');

                return $this->redirectToRoute('admin_settings_analytics');
            }

            return $this->render('admin/settings/analytics.html.twig', [
                'site' => $site,
                'config' => $config,
                'data' => [
                    'enabled' => $enabled,
                    'headCode' => $headCode,
                    'bodyCode' => $bodyCode,
                ],
                'errors' => $errors,
            ]);
        }

        return $this->render('admin/settings/analytics.html.twig', [
            'site' => $site,
            'config' => $config,
            'data' => $config->toArray(),
            'errors' => [],
        ]);
    }

    #[Route('/pages', name: 'admin_settings_pages', methods: ['GET', 'POST'])]
    public function pages(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $pages = SitePageRegistry::pages();
        $content = $this->settingsReader->getPagesContent($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_pages', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $entries = [];
            $formData = [];
            foreach ($pages as $slug => $definition) {
                $key = $definition['settingKey'];
                $html = trim((string) $request->request->get($key, ''));
                $formData[$key] = $html;
                if ($html !== '') {
                    $entries[] = ['pages', $key, $html];
                }
            }

            if ($entries !== []) {
                $this->settingsWriter->setMany($site, $entries);
            }

            $this->addFlash('success', 'Contenido de las páginas del periódico guardado.');

            return $this->redirectToRoute('admin_settings_pages');
        }

        return $this->render('admin/settings/pages.html.twig', [
            'site' => $site,
            'pages' => $pages,
            'content' => $content,
        ]);
    }

    #[Route('/weather', name: 'admin_settings_weather', methods: ['GET', 'POST'])]
    public function weather(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $em = $this->siteEmFactory->forContext($site);
        $webcamRepo = $this->weatherWebcamRepository($em);
        $webcams = $webcamRepo->findAllOrdered();
        $config = $this->settingsReader->getWeatherConfig($site);
        $forecast = null;
        $geocodeError = null;

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', 'save');

            if ($action === 'add_webcam') {
                return $this->handleWebcamAdd($request, $em, $site);
            }
            if ($action === 'delete_webcam') {
                return $this->handleWebcamDelete($request, $em, $site);
            }
            if ($action === 'refresh_forecast') {
                try {
                    $forecast = $this->weatherService->getForecast($site, true);
                    if ($forecast !== null && $config->aiSummaryEnabled) {
                        $this->weatherAiSummaryService->getSummary($site, $forecast, true);
                    }
                    $this->addFlash('success', 'Predicción actualizada.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'No se pudo actualizar: '.$e->getMessage());
                }

                return $this->redirectToRoute('admin_settings_weather');
            }

            if (!$this->isCsrfTokenValid('settings_weather', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $postalCode = preg_replace('/\D/', '', (string) $request->request->get('postalCode', '')) ?? '';
            $aiSummaryEnabled = $request->request->has('aiSummaryEnabled');
            $aiConfig = $this->settingsReader->getAiConfig($site);
            $openaiApiKey = $aiConfig->openaiApiKey !== '' ? $aiConfig->openaiApiKey : $config->openaiApiKey;
            $sponsorData = $this->extractSponsorFormData($request);
            $accommodationsEnabled = $request->request->has('accommodationsEnabled');
            $accommodationsTitle = trim((string) $request->request->get('accommodationsTitle', 'Alojamientos'));
            if ($accommodationsTitle === '') {
                $accommodationsTitle = 'Alojamientos';
            }
            $accommodationsWidgetHtml = (string) $request->request->get('accommodationsWidgetHtml', '');
            $errors = $this->validateWeatherPostal($postalCode, $enabled);
            $errors = array_merge($errors, $this->validateWeatherSponsor($sponsorData));
            if ($accommodationsEnabled && trim($accommodationsWidgetHtml) === '') {
                $errors['accommodationsWidgetHtml'] = 'Pega el código del widget de alojamientos o desactiva la sección.';
            }
            if ($accommodationsEnabled && $postalCode === '') {
                $errors['postalCode'] = $errors['postalCode'] ?? 'Indica un código postal para mostrar alojamientos.';
            }
            if ($aiSummaryEnabled && $openaiApiKey === '' && !$aiConfig->envKeyAvailable) {
                $errors['openaiApiKey'] = 'Configura la API key en Configuración → IA (o OPENAI_API_KEY en el servidor).';
            }

            if ($errors === []) {
                $placeName = $config->placeName;
                $latitude = $config->latitude;
                $longitude = $config->longitude;

                if ($postalCode !== '' && ($postalCode !== $config->postalCode || $latitude === null || $longitude === null)) {
                    try {
                        $geo = $this->weatherService->geocodePostalCode($postalCode);
                        $placeName = $geo['placeName'];
                        $latitude = $geo['latitude'];
                        $longitude = $geo['longitude'];
                    } catch (\Throwable $e) {
                        $errors['postalCode'] = $e->getMessage();
                    }
                }

                if ($errors === []) {
                    $this->settingsWriter->setMany($site, [
                        ['weather', 'enabled', $enabled],
                        ['weather', 'postal_code', $postalCode],
                        ['weather', 'place_name', $placeName],
                        ['weather', 'latitude', $latitude],
                        ['weather', 'longitude', $longitude],
                        ['weather', 'sponsor_headline', $sponsorData['headline']],
                        ['weather', 'sponsor_name', $sponsorData['name']],
                        ['weather', 'sponsor_logo_url', $sponsorData['logoUrl']],
                        ['weather', 'sponsor_link_url', $sponsorData['linkUrl']],
                        ['weather', 'sponsor_banner_url', $sponsorData['bannerUrl']],
                        ['weather', 'sponsor_html', $sponsorData['html']],
                        ['weather', 'sponsor_show_in_nav', $sponsorData['showInNav']],
                        ['weather', 'ai_summary_enabled', $aiSummaryEnabled],
                        ['weather', 'openai_api_key', $openaiApiKey],
                        ['accommodations', 'enabled', $accommodationsEnabled],
                        ['accommodations', 'title', $accommodationsTitle],
                        ['accommodations', 'widget_html', $accommodationsWidgetHtml],
                    ]);

                    if ($enabled && $postalCode !== '') {
                        try {
                            $freshForecast = $this->weatherService->getForecast($site, true);
                            if ($freshForecast !== null && $aiSummaryEnabled && $openaiApiKey !== '') {
                                $this->weatherAiSummaryService->getSummary($site, $freshForecast, true);
                            }
                        } catch (\Throwable) {
                            $this->addFlash('info', 'Configuración guardada. La predicción se actualizará en la primera visita a la sección.');
                        }
                    }

                    $this->addFlash('success', 'Sección del tiempo guardada.');

                    return $this->redirectToRoute('admin_settings_weather');
                }
            }

            return $this->render('admin/settings/weather.html.twig', [
                'site' => $site,
                'config' => $config,
                'accommodations' => $this->settingsReader->getAccommodationsConfig($site),
                'data' => array_merge(
                    [
                        'enabled' => $enabled,
                        'postalCode' => $postalCode,
                        'aiSummaryEnabled' => $aiSummaryEnabled,
                        'accommodationsEnabled' => $accommodationsEnabled,
                        'accommodationsTitle' => $accommodationsTitle,
                        'accommodationsWidgetHtml' => $accommodationsWidgetHtml,
                    ],
                    $sponsorData,
                ),
                'errors' => $errors,
                'webcams' => $webcams,
                'forecast' => $forecast,
                'aiSummary' => null,
                'geocodeError' => $geocodeError,
            ]);
        }

        $aiSummary = null;
        if ($config->isConfigured()) {
            $forecast = $this->weatherService->getForecast($site);
            if ($forecast !== null) {
                $aiSummary = $this->weatherAiSummaryService->getSummary($site, $forecast);
            }
        }

        $accommodations = $this->settingsReader->getAccommodationsConfig($site);

        return $this->render('admin/settings/weather.html.twig', [
            'site' => $site,
            'config' => $config,
            'accommodations' => $accommodations,
            'data' => array_merge($config->toArray(), [
                'accommodationsEnabled' => $accommodations->enabled,
                'accommodationsTitle' => $accommodations->title,
                'accommodationsWidgetHtml' => $accommodations->widgetHtml,
            ]),
            'errors' => [],
            'webcams' => $webcams,
            'forecast' => $forecast,
            'aiSummary' => $aiSummary,
            'geocodeError' => $geocodeError,
        ]);
    }

    #[Route('/markets', name: 'admin_settings_markets', methods: ['GET', 'POST'])]
    public function markets(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getMarketConfig($site);
        $board = null;
        $aiSummary = null;
        $availableIndices = MarketIndexRegistry::all();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', 'save');

            if ($action === 'refresh_board') {
                try {
                    $board = $this->marketService->getBoard($site, true);
                    if ($board !== null && $config->aiSummaryEnabled) {
                        $this->marketAiSummaryService->getSummary($site, $board, true);
                    }
                    $this->addFlash('success', 'Cotizaciones actualizadas.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'No se pudo actualizar: '.$e->getMessage());
                }

                return $this->redirectToRoute('admin_settings_markets');
            }

            if (!$this->isCsrfTokenValid('settings_markets', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $aiSummaryEnabled = $request->request->has('aiSummaryEnabled');
            $aiConfig = $this->settingsReader->getAiConfig($site);
            $openaiApiKey = $aiConfig->openaiApiKey !== '' ? $aiConfig->openaiApiKey : $config->openaiApiKey;
            $selectedIds = $request->request->all('indexIds');
            if (!is_array($selectedIds)) {
                $selectedIds = [];
            }
            $indexIds = MarketIndexRegistry::normalizeIds(array_values(array_filter(
                $selectedIds,
                static fn (mixed $id): bool => is_string($id),
            )));

            $errors = [];
            if ($enabled && $indexIds === []) {
                $errors['indexIds'] = 'Selecciona al menos un índice para mostrar.';
            }
            if ($aiSummaryEnabled && $openaiApiKey === '' && !$aiConfig->envKeyAvailable) {
                $errors['openaiApiKey'] = 'Configura la API key en Configuración → IA (o OPENAI_API_KEY en el servidor).';
            }

            if ($errors === []) {
                $this->settingsWriter->setMany($site, [
                    ['markets', 'enabled', $enabled],
                    ['markets', 'index_ids', $indexIds],
                    ['markets', 'ai_summary_enabled', $aiSummaryEnabled],
                    ['markets', 'openai_api_key', $openaiApiKey],
                ]);

                if ($enabled) {
                    try {
                        $freshBoard = $this->marketService->getBoard($site, true);
                        if ($freshBoard !== null && $aiSummaryEnabled) {
                            $this->marketAiSummaryService->getSummary($site, $freshBoard, true);
                        }
                    } catch (\Throwable) {
                        $this->addFlash('info', 'Configuración guardada. Las cotizaciones se actualizarán en la primera visita a la sección.');
                    }
                }

                $this->addFlash('success', 'Sección de bolsa guardada.');

                return $this->redirectToRoute('admin_settings_markets');
            }

            return $this->render('admin/settings/markets.html.twig', [
                'site' => $site,
                'config' => $config,
                'availableIndices' => $availableIndices,
                'data' => [
                    'enabled' => $enabled,
                    'indexIds' => $indexIds,
                ],
                'errors' => $errors,
                'board' => $board,
            ]);
        }

        if ($config->enabled) {
            $board = $this->marketService->getBoard($site);
            if ($board !== null) {
                $aiSummary = $this->marketAiSummaryService->getSummary($site, $board);
            }
        }

        return $this->render('admin/settings/markets.html.twig', [
            'site' => $site,
            'config' => $config,
            'availableIndices' => $availableIndices,
            'data' => $config->toArray(),
            'errors' => [],
            'board' => $board,
            'aiSummary' => $aiSummary,
        ]);
    }

    #[Route('/tv', name: 'admin_settings_tv', methods: ['GET', 'POST'])]
    public function tv(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getTvConfig($site);
        $schedule = null;
        $availableChannels = TvChannelRegistry::all();

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', 'save');

            if ($action === 'refresh_schedule') {
                try {
                    $this->tvProgrammingService->getSchedule($site, true);
                    $this->addFlash('success', 'Parrilla actualizada.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'No se pudo actualizar: '.$e->getMessage());
                }

                return $this->redirectToRoute('admin_settings_tv');
            }

            if (!$this->isCsrfTokenValid('settings_tv', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $feedUrl = trim((string) $request->request->get('feedUrl', ''));
            $timezone = (string) $request->request->get('timezone', 'Europe/Madrid');
            $selectedIds = $request->request->all('channelIds');
            if (!is_array($selectedIds)) {
                $selectedIds = [];
            }
            $channelIds = TvChannelRegistry::normalizeIds(array_values(array_filter(
                $selectedIds,
                static fn (mixed $id): bool => is_string($id),
            )));

            $errors = [];
            if ($enabled && $channelIds === []) {
                $errors['channelIds'] = 'Selecciona al menos un canal para mostrar.';
            }
            if ($enabled && $feedUrl === '') {
                $errors['feedUrl'] = 'Indica la URL del feed XMLTV.';
            }
            if ($enabled && !in_array($timezone, ['Europe/Madrid', 'Europe/Lisbon', 'Atlantic/Canary'], true)) {
                $errors['timezone'] = 'Zona horaria no válida.';
            }

            if ($errors === []) {
                $this->settingsWriter->setMany($site, [
                    ['tv', 'enabled', $enabled],
                    ['tv', 'channel_ids', $channelIds],
                    ['tv', 'feed_url', $feedUrl !== '' ? $feedUrl : \App\Core\Site\Dto\TvConfig::DEFAULT_FEED_URL],
                    ['tv', 'timezone', $timezone],
                ]);

                if ($enabled) {
                    try {
                        $this->tvProgrammingService->getSchedule($site, true);
                    } catch (\Throwable) {
                        $this->addFlash('info', 'Configuración guardada. La parrilla se actualizará en la primera visita a la sección.');
                    }
                }

                $this->addFlash('success', 'Guía TV guardada.');

                return $this->redirectToRoute('admin_settings_tv');
            }

            return $this->render('admin/settings/tv.html.twig', [
                'site' => $site,
                'config' => $config,
                'availableChannels' => $availableChannels,
                'data' => [
                    'enabled' => $enabled,
                    'channelIds' => $channelIds,
                    'feedUrl' => $feedUrl,
                    'timezone' => $timezone,
                ],
                'errors' => $errors,
                'schedule' => $schedule,
            ]);
        }

        if ($config->enabled) {
            $schedule = $this->tvProgrammingService->getSchedule($site);
        }

        return $this->render('admin/settings/tv.html.twig', [
            'site' => $site,
            'config' => $config,
            'availableChannels' => $availableChannels,
            'data' => $config->toArray(),
            'errors' => [],
            'schedule' => $schedule,
        ]);
    }

    #[Route('/games', name: 'admin_settings_games', methods: ['GET', 'POST'])]
    public function games(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getGamesConfig($site);
        $sudoku = null;
        $anagrama = null;

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', 'save');

            if ($action === 'refresh_puzzles') {
                try {
                    if ($config->sudokuEnabled) {
                        $this->gamesService->getDailySudoku($site, true);
                    }
                    if ($config->wordScrambleEnabled) {
                        $this->gamesService->getDailyWordScramble($site, true);
                    }
                    $this->addFlash('success', 'Retos del día generados.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'No se pudo generar: '.$e->getMessage());
                }

                return $this->redirectToRoute('admin_settings_games');
            }

            if (!$this->isCsrfTokenValid('settings_games', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $sudokuEnabled = $request->request->has('sudokuEnabled');
            $wordScrambleEnabled = $request->request->has('wordScrambleEnabled');

            $errors = [];
            if ($enabled && !$sudokuEnabled && !$wordScrambleEnabled) {
                $errors['games'] = 'Activa al menos un juego.';
            }

            if ($errors === []) {
                $this->settingsWriter->setMany($site, [
                    ['games', 'enabled', $enabled],
                    ['games', 'sudoku_enabled', $sudokuEnabled],
                    ['games', 'word_scramble_enabled', $wordScrambleEnabled],
                ]);

                if ($enabled) {
                    try {
                        if ($sudokuEnabled) {
                            $this->gamesService->getDailySudoku($site, true);
                        }
                        if ($wordScrambleEnabled) {
                            $this->gamesService->getDailyWordScramble($site, true);
                        }
                    } catch (\Throwable) {
                        $this->addFlash('info', 'Configuración guardada. Los retos se generarán en la primera visita.');
                    }
                }

                $this->addFlash('success', 'Pasatiempos guardados.');

                return $this->redirectToRoute('admin_settings_games');
            }

            return $this->render('admin/settings/games.html.twig', [
                'site' => $site,
                'config' => $config,
                'data' => [
                    'enabled' => $enabled,
                    'sudokuEnabled' => $sudokuEnabled,
                    'wordScrambleEnabled' => $wordScrambleEnabled,
                ],
                'errors' => $errors,
                'sudoku' => $sudoku,
                'anagrama' => $anagrama,
            ]);
        }

        if ($config->enabled) {
            if ($config->sudokuEnabled) {
                $sudoku = $this->gamesService->getDailySudoku($site);
            }
            if ($config->wordScrambleEnabled) {
                $anagrama = $this->gamesService->getDailyWordScramble($site);
            }
        }

        return $this->render('admin/settings/games.html.twig', [
            'site' => $site,
            'config' => $config,
            'data' => $config->toArray(),
            'errors' => [],
            'sudoku' => $sudoku,
            'anagrama' => $anagrama,
        ]);
    }

    #[Route('/horoscope', name: 'admin_settings_horoscope', methods: ['GET', 'POST'])]
    public function horoscope(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getHoroscopeConfig($site);
        $board = null;

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('action', 'save');

            if ($action === 'refresh_board') {
                try {
                    $this->horoscopeAiService->getBoard($site, true);
                    $this->addFlash('success', 'Horóscopo regenerado.');
                } catch (\Throwable $e) {
                    $this->addFlash('error', 'No se pudo regenerar: '.$e->getMessage());
                }

                return $this->redirectToRoute('admin_settings_horoscope');
            }

            if (!$this->isCsrfTokenValid('settings_horoscope', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $aiEnabled = $request->request->has('aiEnabled');
            $aiConfig = $this->settingsReader->getAiConfig($site);
            $openaiApiKey = $aiConfig->openaiApiKey !== '' ? $aiConfig->openaiApiKey : $config->openaiApiKey;

            $errors = [];
            if ($aiEnabled && $openaiApiKey === '' && !$aiConfig->envKeyAvailable) {
                $errors['openaiApiKey'] = 'Configura la API key en Configuración → IA (o OPENAI_API_KEY en el servidor).';
            }

            if ($errors === []) {
                $this->settingsWriter->setMany($site, [
                    ['horoscope', 'enabled', $enabled],
                    ['horoscope', 'ai_enabled', $aiEnabled],
                    ['horoscope', 'openai_api_key', $openaiApiKey],
                ]);

                if ($enabled) {
                    try {
                        $this->horoscopeAiService->getBoard($site, true);
                    } catch (\Throwable) {
                        $this->addFlash('info', 'Configuración guardada. El horóscopo se generará en la primera visita.');
                    }
                }

                $this->addFlash('success', 'Horóscopo guardado.');

                return $this->redirectToRoute('admin_settings_horoscope');
            }

            return $this->render('admin/settings/horoscope.html.twig', [
                'site' => $site,
                'config' => $config,
                'data' => [
                    'enabled' => $enabled,
                    'aiEnabled' => $aiEnabled,
                ],
                'errors' => $errors,
                'board' => $board,
            ]);
        }

        if ($config->enabled) {
            $board = $this->horoscopeAiService->getBoard($site);
        }

        return $this->render('admin/settings/horoscope.html.twig', [
            'site' => $site,
            'config' => $config,
            'data' => $config->toArray(),
            'errors' => [],
            'board' => $board,
        ]);
    }

    #[Route('/navigation', name: 'admin_settings_navigation', methods: ['GET', 'POST'])]
    public function navigation(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $em = $this->siteEmFactory->forContext($site);
        $config = $this->settingsReader->getNavMenuConfig($site);
        $categories = $this->loadCategoriesForNav($em);
        $normalizer = new NavMenuNormalizer();
        $menuTypes = \App\Navigation\NavMenuItemType::cases();
        $pages = \App\Editorial\SitePageRegistry::pages();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_navigation', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $mainRows = $request->request->all('mainItems');
            $footerSectionRows = $request->request->all('footerSectionItems');
            $footerLegalRows = $request->request->all('footerLegalItems');

            $navConfig = $normalizer->toConfig(
                is_array($mainRows) ? array_values($mainRows) : [],
                is_array($footerSectionRows) ? array_values($footerSectionRows) : [],
                is_array($footerLegalRows) ? array_values($footerLegalRows) : [],
            );

            $this->settingsWriter->setMany($site, [
                ['navigation', 'main_json', json_encode($navConfig->mainItems, JSON_THROW_ON_ERROR)],
                ['navigation', 'footer_sections_json', json_encode($navConfig->footerSectionItems, JSON_THROW_ON_ERROR)],
                ['navigation', 'footer_legal_json', json_encode($navConfig->footerLegalItems, JSON_THROW_ON_ERROR)],
            ]);

            $this->addFlash('success', 'Menús guardados.');

            return $this->redirectToRoute('admin_settings_navigation');
        }

        return $this->render('admin/settings/navigation.html.twig', [
            'site' => $site,
            'config' => $config,
            'categories' => $categories,
            'menuTypes' => $menuTypes,
            'pages' => $pages,
            'defaults' => [
                'main' => NavMenuDefaults::mainItems(),
                'footerSections' => NavMenuDefaults::footerSectionItems(),
                'footerLegal' => NavMenuDefaults::footerLegalItems(),
            ],
            'errors' => [],
        ]);
    }

    /** @return list<\App\Entity\Site\Category> */
    private function loadCategoriesForNav(\Doctrine\ORM\EntityManagerInterface $em): array
    {
        $repo = $em->getRepository(\App\Entity\Site\Category::class);
        if ($repo instanceof \App\Repository\Site\CategoryRepository) {
            return $repo->findRootsForAdmin();
        }

        return $repo->findBy([], ['name' => 'ASC']);
    }

    private function handleWebcamAdd(Request $request, \Doctrine\ORM\EntityManagerInterface $em, \App\Core\Site\SiteContext $site): Response
    {
        if (!$this->isCsrfTokenValid('weather_webcam_add', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $title = trim((string) $request->request->get('webcamTitle', ''));
        $url = trim((string) $request->request->get('webcamUrl', ''));
        $type = (string) $request->request->get('webcamType', 'embed');

        if ($title === '' || $url === '') {
            $this->addFlash('error', 'Título y URL son obligatorios para la webcam.');
        } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->addFlash('error', 'URL de webcam no válida.');
        } else {
            $webcam = new WeatherWebcam($title, $url, $type === 'image' ? 'image' : 'embed');
            $webcam->setSortOrder((int) $request->request->get('webcamSort', 0));
            $webcam->setEnabled($request->request->has('webcamEnabled'));
            $em->persist($webcam);
            $em->flush();
            $this->addFlash('success', 'Webcam añadida.');
        }

        return $this->redirectToRoute('admin_settings_weather');
    }

    private function handleWebcamDelete(Request $request, \Doctrine\ORM\EntityManagerInterface $em, \App\Core\Site\SiteContext $site): Response
    {
        $id = (int) $request->request->get('webcamId', 0);
        if (!$this->isCsrfTokenValid('weather_webcam_delete'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $webcam = $em->find(WeatherWebcam::class, $id);
        if ($webcam !== null) {
            $em->remove($webcam);
            $em->flush();
            $this->addFlash('success', 'Webcam eliminada.');
        }

        return $this->redirectToRoute('admin_settings_weather');
    }

    private function weatherWebcamRepository(\Doctrine\ORM\EntityManagerInterface $em): WeatherWebcamRepository
    {
        $repo = $em->getRepository(WeatherWebcam::class);
        if (!$repo instanceof WeatherWebcamRepository) {
            throw new \RuntimeException('WeatherWebcamRepository no disponible.');
        }

        return $repo;
    }

    /** @return array<string, mixed> */
    private function extractSponsorFormData(Request $request): array
    {
        return [
            'headline' => trim((string) $request->request->get('sponsorHeadline', '')),
            'name' => trim((string) $request->request->get('sponsorName', '')),
            'logoUrl' => trim((string) $request->request->get('sponsorLogoUrl', '')),
            'linkUrl' => trim((string) $request->request->get('sponsorLinkUrl', '')),
            'bannerUrl' => trim((string) $request->request->get('sponsorBannerUrl', '')),
            'html' => trim((string) $request->request->get('sponsorHtml', '')),
            'showInNav' => $request->request->has('sponsorShowInNav'),
        ];
    }

    /** @return array<string, string> */
    private function validateWeatherPostal(string $postalCode, bool $enabled): array
    {
        $errors = [];
        if ($postalCode !== '' && strlen($postalCode) !== 5) {
            $errors['postalCode'] = 'El código postal debe tener 5 dígitos.';
        }
        if ($enabled && $postalCode === '') {
            $errors['postalCode'] = 'Indica un código postal para activar el tiempo.';
        }

        return $errors;
    }

    /** @param array<string, mixed> $data */
    private function validateWeatherSponsor(array $data): array
    {
        $errors = [];
        foreach ([
            'logoUrl' => 'sponsorLogoUrl',
            'linkUrl' => 'sponsorLinkUrl',
            'bannerUrl' => 'sponsorBannerUrl',
        ] as $field => $errorKey) {
            $url = (string) ($data[$field] ?? '');
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[$errorKey] = 'URL no válida.';
            }
        }

        return $errors;
    }

    #[Route('/general', name: 'admin_settings_general', methods: ['GET', 'POST'])]
    public function general(Request $request): Response
    {
        [$siteContext] = $this->requireSiteAccess();
        $platformSite = $this->platformEm->find(Site::class, $siteContext->id);
        if ($platformSite === null) {
            throw $this->createNotFoundException('Periódico no encontrado.');
        }

        $primaryDomain = '';
        foreach ($platformSite->getDomains() as $domain) {
            if ($domain->isPrimary()) {
                $primaryDomain = $domain->getDomain();
                break;
            }
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_general', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $name = trim((string) $request->request->get('name', ''));
            $domain = strtolower(trim((string) $request->request->get('domain', '')));
            $locale = (string) $request->request->get('locale', 'es');
            $timezone = (string) $request->request->get('timezone', 'Europe/Madrid');

            $errors = [];
            if ($name === '') {
                $errors['name'] = 'El nombre es obligatorio.';
            }
            if ($domain === '') {
                $errors['domain'] = 'El dominio es obligatorio.';
            }

            if ($errors === []) {
                $platformSite->setName($name)->setDefaultLocale($locale)->setTimezone($timezone);
                foreach ($platformSite->getDomains() as $siteDomain) {
                    if ($siteDomain->isPrimary()) {
                        $siteDomain->setDomain($domain);
                    }
                }
                $this->platformEm->flush();

                $this->addFlash('success', 'Datos del periódico guardados.');

                return $this->redirectToRoute('admin_settings_general');
            }

            return $this->render('admin/settings/general.html.twig', [
                'site' => $siteContext,
                'data' => compact('name', 'domain', 'locale', 'timezone') + ['code' => $platformSite->getCode()],
                'errors' => $errors,
            ]);
        }

        return $this->render('admin/settings/general.html.twig', [
            'site' => $siteContext,
            'data' => [
                'code' => $platformSite->getCode(),
                'name' => $platformSite->getName(),
                'domain' => $primaryDomain,
                'locale' => $platformSite->getDefaultLocale(),
                'timezone' => $platformSite->getTimezone(),
            ],
            'errors' => [],
        ]);
    }

    /** @param array<string, string> $data */
    private function validateAppearanceColors(array $data): array
    {
        $errors = [];
        $pattern = '/^#[0-9a-fA-F]{6}$/';

        $required = [
            'primaryColor' => 'Color primario',
            'secondaryColor' => 'Color secundario',
            'backgroundColor' => 'Color de fondo',
            'surfaceColor' => 'Color de superficie',
            'textColor' => 'Color de texto',
            'textMutedColor' => 'Color de texto secundario',
            'headingColor' => 'Color de titulares',
            'borderColor' => 'Color de bordes',
            'topBarColor' => 'Color barra superior',
            'navBarColor' => 'Color del menú',
            'footerColor' => 'Color del pie',
        ];

        foreach ($required as $field => $label) {
            if (!preg_match($pattern, $data[$field] ?? '')) {
                $errors[$field] = $label.' inválido.';
            }
        }

        if (($data['linkColor'] ?? '') !== '' && !preg_match($pattern, $data['linkColor'])) {
            $errors['linkColor'] = 'Color de enlaces inválido.';
        }
        if (($data['navColor'] ?? '') !== '' && !preg_match($pattern, $data['navColor'])) {
            $errors['navColor'] = 'Color de navegación inválido.';
        }

        return $errors;
    }

    private function normalizeMetaKeywords(string $raw): string
    {
        $parts = preg_split('/\s*,\s*/u', trim($raw)) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            $word = trim((string) $part);
            if ($word === '') {
                continue;
            }
            $word = mb_substr($word, 0, 80);
            $key = mb_strtolower($word);
            if (isset($clean[$key])) {
                continue;
            }
            $clean[$key] = $word;
            if (count($clean) >= 40) {
                break;
            }
        }

        return implode(', ', array_values($clean));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function validateSeoSettings(array $data): array
    {
        $errors = [];

        if (!array_key_exists((string) $data['defaultRobots'], \App\Core\Site\Dto\SiteSeoConfig::ROBOTS_PRESETS)) {
            $errors['defaultRobots'] = 'Directiva robots no válida.';
        }
        if (!array_key_exists((string) $data['organizationType'], \App\Core\Site\Dto\SiteSeoConfig::ORGANIZATION_TYPES)) {
            $errors['organizationType'] = 'Tipo de organización no válido.';
        }
        if (mb_strlen((string) $data['metaDescription']) > 500) {
            $errors['metaDescription'] = 'La meta descripción no puede superar 500 caracteres.';
        }
        if (mb_strlen((string) $data['homeTitle']) > 120) {
            $errors['homeTitle'] = 'El título de portada no puede superar 120 caracteres.';
        }
        if (mb_strlen((string) $data['titleSeparator']) > 10) {
            $errors['titleSeparator'] = 'El separador de título es demasiado largo.';
        }
        if ($data['themeColor'] !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $data['themeColor'])) {
            $errors['themeColor'] = 'El color del tema debe ser un hex #RRGGBB.';
        }
        if ($data['organizationEmail'] !== '' && !filter_var((string) $data['organizationEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors['organizationEmail'] = 'Email de organización no válido.';
        }
        if ($data['foundingDate'] !== '' && !preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', (string) $data['foundingDate'])) {
            $errors['foundingDate'] = 'Usa formato AAAA, AAAA-MM o AAAA-MM-DD.';
        }
        foreach (['defaultOgImage', 'organizationLogoUrl'] as $urlField) {
            $url = (string) $data[$urlField];
            if ($url === '') {
                continue;
            }
            if (!str_starts_with($url, '/') && !preg_match('#^https?://#i', $url)) {
                $errors[$urlField] = 'Usa una URL absoluta (https://…) o una ruta relativa (/media/…).';
            }
        }
        if (mb_strlen((string) $data['extraHeadHtml']) > 20000) {
            $errors['extraHeadHtml'] = 'El HTML extra del head es demasiado largo.';
        }
        if (mb_strlen((string) $data['robotsExtra']) > 4000) {
            $errors['robotsExtra'] = 'Las líneas extra de robots.txt son demasiado largas.';
        }

        return $errors;
    }

    /** @return array<string, string> */
    private function validateIngest(
        bool $enabled,
        string $jsonUrl,
        string $apiKey,
        int $intervalMinutes,
        int $publishStaggerMinutes,
    ): array
    {
        $errors = [];

        if ($enabled && $jsonUrl === '') {
            $errors['jsonUrl'] = 'La URL JSON es obligatoria si la ingesta automática está activada.';
        }

        if ($enabled && $apiKey === '') {
            $errors['apiKey'] = 'La API Key es obligatoria si la ingesta automática está activada.';
        }

        if ($jsonUrl !== '' && !filter_var($jsonUrl, FILTER_VALIDATE_URL)) {
            $errors['jsonUrl'] = 'URL no válida.';
        }

        if ($jsonUrl !== '' && !preg_match('#^https?://#i', $jsonUrl)) {
            $errors['jsonUrl'] = 'La URL debe comenzar por http:// o https://';
        }

        if (!isset(self::INTERVAL_OPTIONS[$intervalMinutes])) {
            $errors['intervalMinutes'] = 'Periodicidad no válida.';
        }

        if (!isset(self::PUBLISH_STAGGER_OPTIONS[$publishStaggerMinutes])) {
            $errors['publishStaggerMinutes'] = 'Intervalo de publicación no válido.';
        }

        return $errors;
    }

    /** @param array<string, string> $data */
    private function validateFooter(array $data): array
    {
        $errors = [];

        if ($data['contactEmail'] !== '' && !filter_var($data['contactEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors['contactEmail'] = 'Email de contacto no válido.';
        }

        foreach (SiteSocialNetworkRegistry::networks() as $network) {
            $field = $network['key'].'Url';
            if ($data[$field] !== '' && !filter_var($data[$field], FILTER_VALIDATE_URL)) {
                $errors[$field] = 'URL de '.$network['label'].' no válida.';
            }
        }

        return $errors;
    }

    /** @param array<string, mixed> $data */
    private function validateMail(array $data): array
    {
        $errors = [];
        $enabled = (bool) ($data['enabled'] ?? false);

        if ($enabled) {
            if ($data['host'] === '') {
                $errors['host'] = 'Indica el servidor SMTP.';
            }
            if ($data['port'] < 1 || $data['port'] > 65535) {
                $errors['port'] = 'Puerto no válido.';
            }
            if (!\in_array($data['encryption'], ['tls', 'ssl', 'none'], true)) {
                $errors['encryption'] = 'Cifrado no válido.';
            }
            if ($data['username'] === '') {
                $errors['username'] = 'Indica el usuario SMTP (email de Google).';
            }
            if ($data['password'] === '') {
                $errors['password'] = 'Indica la contraseña de aplicación.';
            } elseif (SiteMailConfig::isGmailHost($data['host']) && !SiteMailConfig::isGmailAppPassword($data['password'])) {
                $errors['password'] = 'Gmail ya no acepta la contraseña de la cuenta. Crea una contraseña de aplicación de 16 letras en Google Cuenta → Seguridad → Contraseñas de aplicaciones y pégala aquí (los espacios se quitan solos).';
            }
        }

        if ($data['fromEmail'] !== '' && !filter_var($data['fromEmail'], FILTER_VALIDATE_EMAIL)) {
            $errors['fromEmail'] = 'Email remitente no válido.';
        }
        if ($data['contactRecipient'] !== '' && !filter_var($data['contactRecipient'], FILTER_VALIDATE_EMAIL)) {
            $errors['contactRecipient'] = 'Email de destino del formulario no válido.';
        }

        return $errors;
    }

    private function formatMailTestError(\Throwable $e): string
    {
        $message = $e->getMessage();
        if (
            str_contains($message, '535')
            || str_contains($message, 'BadCredentials')
            || str_contains($message, 'Username and Password not accepted')
        ) {
            return 'Gmail ha rechazado el usuario o la contraseña. No uses la contraseña de la cuenta de Google: activa la verificación en 2 pasos, crea una contraseña de aplicación de 16 letras y vuelve a guardarla en este formulario.';
        }

        return 'No se pudo enviar la prueba: '.$message;
    }

    #[Route('/classifieds', name: 'admin_settings_classifieds', methods: ['GET', 'POST'])]
    public function classifieds(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getClassifiedsConfig($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_classifieds', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $this->settingsWriter->setMany($site, [
                ['classifieds', 'enabled', $enabled],
            ]);
            $this->addFlash('success', 'Avisos guardados.');

            return $this->redirectToRoute('admin_settings_classifieds');
        }

        return $this->render('admin/settings/classifieds.html.twig', [
            'site' => $site,
            'config' => $config,
            'data' => $config->toArray(),
        ]);
    }

    #[Route('/forum', name: 'admin_settings_forum', methods: ['GET', 'POST'])]
    public function forum(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getForumConfig($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_forum', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $enabled = $request->request->has('enabled');
            $this->settingsWriter->setMany($site, [
                ['forum', 'enabled', $enabled],
            ]);
            $this->addFlash('success', 'Foro guardado.');

            return $this->redirectToRoute('admin_settings_forum');
        }

        return $this->render('admin/settings/forum.html.twig', [
            'site' => $site,
            'config' => $config,
            'data' => $config->toArray(),
        ]);
    }

    #[Route('/error-404', name: 'admin_settings_error_404', methods: ['GET', 'POST'])]
    public function error404(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();
        $config = $this->settingsReader->getError404Config($site);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_error_404', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $title = trim((string) $request->request->get('title', ''));
            $message = trim((string) $request->request->get('message', ''));
            $searchPlaceholder = trim((string) $request->request->get('searchPlaceholder', ''));
            $homeButtonLabel = trim((string) $request->request->get('homeButtonLabel', ''));
            $extraHtml = (string) $request->request->get('extraHtml', '');

            if ($title === '') {
                $title = 'Página no encontrada';
            }
            if ($message === '') {
                $message = 'La dirección que buscas no existe, se ha movido o ya no está disponible.';
            }
            if ($searchPlaceholder === '') {
                $searchPlaceholder = 'Buscar noticias…';
            }
            if ($homeButtonLabel === '') {
                $homeButtonLabel = 'Ir a la portada';
            }

            $this->settingsWriter->setMany($site, [
                ['error404', 'title', $title],
                ['error404', 'message', $message],
                ['error404', 'show_search', $request->request->has('showSearch')],
                ['error404', 'show_home_button', $request->request->has('showHomeButton')],
                ['error404', 'show_latest_articles', $request->request->has('showLatestArticles')],
                ['error404', 'search_placeholder', $searchPlaceholder],
                ['error404', 'home_button_label', $homeButtonLabel],
                ['error404', 'extra_html', $extraHtml],
            ]);
            $this->addFlash('success', 'Página 404 guardada.');

            return $this->redirectToRoute('admin_settings_error_404');
        }

        return $this->render('admin/settings/error_404.html.twig', [
            'site' => $site,
            'config' => $config,
            'data' => $config->toArray(),
        ]);
    }

    #[Route('/error-404/preview', name: 'admin_settings_error_404_preview', methods: ['GET'])]
    public function error404Preview(\App\Core\Site\Error404PageRenderer $renderer): Response
    {
        $this->requireSiteAccess();
        $response = $renderer->render('/ejemplo-no-existe', preview: true);
        if ($response === null) {
            throw $this->createNotFoundException('No hay contexto de sitio para previsualizar.');
        }

        return $response;
    }

    #[Route('/transfer', name: 'admin_settings_transfer', methods: ['GET', 'POST'])]
    public function transfer(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();

        $sectionLabels = SiteConfigTransferService::SECTION_LABELS;
        $sectionGroups = SiteConfigTransferService::SECTION_GROUPS;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_transfer', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $sections = $request->request->all('sections');
            $sections = \is_array($sections) ? array_values(array_map('strval', $sections)) : [];
            $dryRun = $request->request->getBoolean('dryRun');
            $includeSecrets = $request->request->getBoolean('includeSecrets');

            $file = $request->files->get('configFile');
            if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile || !$file->isValid()) {
                $this->addFlash('error', 'Sube un archivo JSON de exportación válido.');

                return $this->redirectToRoute('admin_settings_transfer');
            }

            $raw = @file_get_contents($file->getPathname());
            if ($raw === false || trim($raw) === '') {
                $this->addFlash('error', 'No se pudo leer el archivo subido.');

                return $this->redirectToRoute('admin_settings_transfer');
            }

            try {
                $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (!\is_array($payload)) {
                    throw new \InvalidArgumentException('JSON inválido.');
                }
                $result = $this->configTransfer->import($site, $payload, $sections, [
                    'dryRun' => $dryRun,
                    'includeSecrets' => $includeSecrets,
                ]);
            } catch (\JsonException|\InvalidArgumentException|\RuntimeException $e) {
                $this->addFlash('error', 'Importación fallida: '.$e->getMessage());

                return $this->redirectToRoute('admin_settings_transfer');
            }

            $names = array_map(
                static fn (string $key): string => $sectionLabels[$key] ?? $key,
                $result['imported'],
            );
            $countBits = [];
            foreach ($result['counts'] as $key => $n) {
                if (!\is_int($n) || str_starts_with((string) $key, '_')) {
                    continue;
                }
                $countBits[] = ($sectionLabels[$key] ?? $key).': '.$n;
            }
            $prefix = $result['dryRun'] ? 'Simulación (sin cambios)' : 'Configuración importada';
            $this->addFlash(
                $result['dryRun'] ? 'info' : 'success',
                $prefix.': '.implode(', ', $names)
                .($countBits !== [] ? ' ('.implode('; ', $countBits).')' : '').'.',
            );
            foreach ($result['warnings'] as $warning) {
                $this->addFlash('info', $warning);
            }

            return $this->redirectToRoute('admin_settings_transfer');
        }

        return $this->render('admin/settings/transfer.html.twig', [
            'site' => $site,
            'sectionLabels' => $sectionLabels,
            'sectionGroups' => $sectionGroups,
            'defaultSections' => SiteConfigTransferService::allSections(),
        ]);
    }

    #[Route('/transfer/export', name: 'admin_settings_transfer_export', methods: ['GET', 'POST'])]
    public function transferExport(Request $request): Response
    {
        [$site] = $this->requireSiteAccess();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('settings_transfer_export', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }
            $sections = $request->request->all('sections');
            $sections = \is_array($sections) ? array_values(array_map('strval', $sections)) : [];
            $includeSecrets = $request->request->getBoolean('includeSecrets');
            $includeCaches = $request->request->getBoolean('includeCaches');
        } else {
            $sectionsParam = (string) $request->query->get('sections', '');
            $sections = $sectionsParam !== ''
                ? array_values(array_filter(array_map('trim', explode(',', $sectionsParam))))
                : SiteConfigTransferService::allSections();
            $includeSecrets = $request->query->getBoolean('secrets');
            $includeCaches = $request->query->getBoolean('caches');
        }

        try {
            $json = $this->configTransfer->exportJson($site, $sections, $includeSecrets, $includeCaches);
        } catch (\JsonException|\InvalidArgumentException $e) {
            $this->addFlash('error', 'No se pudo generar la exportación: '.$e->getMessage());

            return $this->redirectToRoute('admin_settings_transfer');
        }

        $filename = sprintf(
            'mymediacms-config-%s-%s.json',
            preg_replace('/[^a-z0-9_-]+/i', '-', $site->code) ?: 'site',
            (new \DateTimeImmutable('now'))->format('Ymd-His'),
        );

        $response = new Response($json, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
        );

        return $response;
    }
}
