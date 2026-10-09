<?php

namespace App\Core\Site;

use App\Entity\Site\AdBanner;
use App\Entity\Site\ArticleTag;
use App\Entity\Site\Category;
use App\Entity\Site\ClassifiedCategory;
use App\Entity\Site\Enum\AdCreativeType;
use App\Entity\Site\Enum\AdFormat;
use App\Entity\Site\Enum\AdPlacement;
use App\Entity\Site\Enum\SiteLinkGroup;
use App\Entity\Site\ForumCategory;
use App\Entity\Site\Gallery;
use App\Entity\Site\GalleryImage;
use App\Entity\Site\Poll;
use App\Entity\Site\PollOption;
use App\Entity\Site\SiteLink;
use App\Entity\Site\SiteSetting;
use App\Entity\Site\WeatherWebcam;
use App\Navigation\NavMenuNormalizer;
use App\Repository\Site\CategoryRepository;
use App\Repository\Site\SiteLinkRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Exporta e importa la configuración del periódico entre entornos.
 *
 * Incluye settings de módulos, menús (con slugs), categorías, enlaces, anuncios,
 * encuestas (sin votos), galerías (URLs), etiquetas, webcams y categorías de foro/clasificados.
 * Por defecto omite secretos y caches; las noticias y binarios de media no se transfieren.
 */
final class SiteConfigTransferService
{
    public const VERSION = 2;

    /** @var array<string, string> */
    public const SECTION_LABELS = [
        'categories' => 'Categorías de noticias',
        'footer' => 'Pie y contacto',
        'seo' => 'SEO',
        'appearance' => 'Apariencia (colores, tipografías, logos)',
        'layout' => 'Plantillas',
        'navigation' => 'Menús',
        'pages' => 'Páginas estáticas',
        'error404' => 'Página 404',
        'analytics' => 'Estadísticas / analítica',
        'links' => 'Enlaces (otros medios / interés)',
        'ai' => 'Inteligencia artificial',
        'weather' => 'El tiempo',
        'webcams' => 'Webcams del tiempo',
        'accommodations' => 'Alojamientos',
        'markets' => 'Mercados',
        'tv' => 'Programación TV',
        'games' => 'Juegos',
        'horoscope' => 'Horóscopo',
        'classifieds' => 'Clasificados (ajuste on/off)',
        'classified_categories' => 'Categorías de clasificados',
        'forum' => 'Foro (ajuste on/off)',
        'forum_categories' => 'Categorías del foro',
        'mail' => 'Correo SMTP',
        'ingest' => 'Ingesta HimtecMedia',
        'permissions' => 'Permisos de usuarios (matriz por rol)',
        'ads' => 'Banners publicitarios',
        'polls' => 'Encuestas (sin votos)',
        'galleries' => 'Galerías (URLs de imágenes)',
        'tags' => 'Etiquetas',
    ];

    /** @var array<string, list<string>> */
    public const SECTION_GROUPS = [
        'Básico' => [
            'categories', 'footer', 'seo', 'appearance', 'layout', 'navigation',
            'pages', 'error404', 'analytics', 'links',
        ],
        'Módulos' => [
            'ai', 'weather', 'webcams', 'accommodations', 'markets', 'tv', 'games',
            'horoscope', 'classifieds', 'classified_categories', 'forum', 'forum_categories',
            'mail', 'ingest', 'permissions',
        ],
        'Contenido auxiliar' => [
            'ads', 'polls', 'galleries', 'tags',
        ],
    ];

    /** Secciones → namespaces de site_settings. */
    private const SECTION_NAMESPACES = [
        'footer' => ['footer'],
        'seo' => ['seo'],
        'appearance' => ['theme', 'branding', 'identity'],
        'layout' => ['layout'],
        'content_modules' => ['content_modules'],
        'navigation' => ['navigation'],
        'pages' => ['pages'],
        'error404' => ['error404'],
        'analytics' => ['analytics'],
        'ai' => ['ai'],
        'weather' => ['weather'],
        'accommodations' => ['accommodations'],
        'markets' => ['markets'],
        'tv' => ['tv'],
        'games' => ['games'],
        'horoscope' => ['horoscope'],
        'classifieds' => ['classifieds'],
        'forum' => ['forum'],
        'mail' => ['mail'],
        'ingest' => ['ingest'],
        'permissions' => ['permissions'],
    ];

    /** namespace.key que son secretos (omitidos salvo includeSecrets). */
    private const SECRET_KEYS = [
        'mail.smtp_password',
        'ai.openai_api_key',
        'weather.openai_api_key',
        'markets.openai_api_key',
        'horoscope.openai_api_key',
        'ingest.himtecmedia_api_key',
    ];

    /** namespace.key de caches / estado runtime (omitidos salvo includeCaches). */
    private const CACHE_KEYS = [
        'weather.cached_at',
        'weather.cached_json',
        'weather.ai_summary_json',
        'weather.ai_summary_cached_at',
        'weather.ai_summary_forecast_at',
        'markets.cached_at',
        'markets.cached_json',
        'markets.ai_summary_json',
        'markets.ai_summary_cached_at',
        'markets.ai_summary_board_at',
        'tv.cached_at',
        'tv.cached_json',
        'games.sudoku_cached_json',
        'games.sudoku_cached_date',
        'games.word_scramble_cached_json',
        'games.word_scramble_cached_date',
        'horoscope.daily_json',
        'horoscope.daily_cached_at',
        'horoscope.daily_date_key',
        'ingest.himtecmedia_last_fetched_at',
        'ingest.himtecmedia_last_fetch_status',
        'ingest.himtecmedia_last_fetch_count',
        'ingest.himtecmedia_last_fetch_duplicates',
    ];

    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly SiteSettingsWriter $settingsWriter,
    ) {}

    /** @return list<string> */
    public static function allSections(): array
    {
        return array_keys(self::SECTION_LABELS);
    }

    /**
     * @param list<string>|null $sections
     *
     * @return array<string, mixed>
     */
    public function export(
        SiteContext $site,
        ?array $sections = null,
        bool $includeSecrets = false,
        bool $includeCaches = false,
    ): array {
        $sections = $this->normalizeSections($sections ?? self::allSections());
        $em = $this->siteEmFactory->forContext($site);
        $categoriesById = $this->loadCategoriesIndexed($em);

        $payloadSections = [];
        $omittedSecrets = [];
        $omittedCaches = [];

        if (\in_array('categories', $sections, true)) {
            $payloadSections['categories'] = $this->exportCategories($categoriesById);
        }

        $settingsNeeded = false;
        foreach (array_keys(self::SECTION_NAMESPACES) as $section) {
            if (\in_array($section, $sections, true)) {
                $settingsNeeded = true;
                break;
            }
        }
        if ($settingsNeeded) {
            [$settings, $secretKeys, $cacheKeys] = $this->exportSettings(
                $em,
                $categoriesById,
                $sections,
                $includeSecrets,
                $includeCaches,
            );
            $payloadSections['settings'] = $settings;
            $omittedSecrets = $secretKeys;
            $omittedCaches = $cacheKeys;
        }

        if (\in_array('links', $sections, true)) {
            $payloadSections['links'] = $this->exportLinks($em);
        }
        if (\in_array('ads', $sections, true)) {
            $payloadSections['ads'] = $this->exportAds($em);
        }
        if (\in_array('polls', $sections, true)) {
            $payloadSections['polls'] = $this->exportPolls($em);
        }
        if (\in_array('galleries', $sections, true)) {
            $payloadSections['galleries'] = $this->exportGalleries($em);
        }
        if (\in_array('tags', $sections, true)) {
            $payloadSections['tags'] = $this->exportTags($em);
        }
        if (\in_array('webcams', $sections, true)) {
            $payloadSections['webcams'] = $this->exportWebcams($em);
        }
        if (\in_array('forum_categories', $sections, true)) {
            $payloadSections['forum_categories'] = $this->exportForumCategories($em);
        }
        if (\in_array('classified_categories', $sections, true)) {
            $payloadSections['classified_categories'] = $this->exportClassifiedCategories($em);
        }

        $notes = [
            'Las URLs de logos/medios son rutas públicas; copia /media si quieres las mismas imágenes.',
            'No incluye noticias, comentarios, clasificados publicados, hilos del foro ni binarios.',
            'Las encuestas se exportan sin votos (conteos a cero).',
        ];
        if ($omittedSecrets !== []) {
            $notes[] = 'Secretos omitidos: '.implode(', ', $omittedSecrets).'. Activa «Incluir secretos» para exportarlos.';
        }
        if ($omittedCaches !== []) {
            $notes[] = 'Caches omitidas (se regeneran solas).';
        }

        return [
            'format' => 'mymediacms-site-config',
            'version' => self::VERSION,
            'siteCode' => $site->code,
            'siteName' => $site->name,
            'exportedAt' => (new \DateTimeImmutable('now'))->format(\DateTimeInterface::ATOM),
            'options' => [
                'includeSecrets' => $includeSecrets,
                'includeCaches' => $includeCaches,
                'exportedSections' => $sections,
            ],
            'sections' => $payloadSections,
            'notes' => $notes,
        ];
    }

    /**
     * @param list<string>|null $sections
     */
    public function exportJson(
        SiteContext $site,
        ?array $sections = null,
        bool $includeSecrets = false,
        bool $includeCaches = false,
    ): string {
        return json_encode(
            $this->export($site, $sections, $includeSecrets, $includeCaches),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        )."\n";
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string>         $sections
     * @param array{dryRun?: bool, includeSecrets?: bool} $options
     *
     * @return array{imported: list<string>, counts: array<string, int>, warnings: list<string>, dryRun: bool}
     */
    public function import(SiteContext $site, array $payload, array $sections, array $options = []): array
    {
        $this->assertPayload($payload);

        $dryRun = (bool) ($options['dryRun'] ?? false);
        $includeSecrets = (bool) ($options['includeSecrets'] ?? false);
        $sections = $this->normalizeSections($sections);
        if ($sections === []) {
            throw new \InvalidArgumentException('Selecciona al menos una sección para importar.');
        }

        $em = $this->siteEmFactory->forContext($site);
        $imported = [];
        $counts = [];
        $warnings = [];

        if (\in_array('categories', $sections, true)) {
            $counts['categories'] = $this->importCategories($em, $payload['sections']['categories'] ?? [], $dryRun);
            $imported[] = 'categories';
        }

        $categoriesBySlug = $this->loadCategoriesBySlug($em);
        $settingsEntries = [];
        $settingsPayload = $payload['sections']['settings'] ?? [];
        if (!\is_array($settingsPayload)) {
            $settingsPayload = [];
        }

        foreach (self::SECTION_NAMESPACES as $section => $namespaces) {
            if (!\in_array($section, $sections, true)) {
                continue;
            }
            if ($section === 'navigation') {
                $nav = $settingsPayload['navigation'] ?? null;
                if (\is_array($nav)) {
                    [$navEntries, $navWarnings] = $this->prepareNavigationImport($nav, $categoriesBySlug);
                    foreach ($navEntries as $entry) {
                        $settingsEntries[] = $entry;
                    }
                    foreach ($navWarnings as $w) {
                        $warnings[] = $w;
                    }
                    $imported[] = 'navigation';
                    $counts['navigation'] = 3;
                }
                continue;
            }

            $sectionCount = 0;
            foreach ($namespaces as $ns) {
                $nsData = $settingsPayload[$ns] ?? null;
                if (!\is_array($nsData)) {
                    continue;
                }
                foreach ($nsData as $key => $value) {
                    if (!\is_string($key) || $key === '') {
                        continue;
                    }
                    $fullKey = $ns.'.'.$key;
                    if (!$includeSecrets && \in_array($fullKey, self::SECRET_KEYS, true)) {
                        $warnings[] = sprintf('Secreto «%s» omitido (marca «Incluir secretos» para aplicarlo).', $fullKey);
                        continue;
                    }
                    if (\in_array($fullKey, self::CACHE_KEYS, true) && !$this->payloadIncludesCaches($payload)) {
                        // Si el archivo trae caches las aplicamos; si no, no inventamos.
                    }
                    $settingsEntries[] = [$ns, $key, $value];
                    ++$sectionCount;
                }
                if ($section === 'appearance') {
                    $counts[$ns] = \is_array($nsData) ? \count($nsData) : 0;
                }
            }
            if ($sectionCount > 0 || $section === 'appearance') {
                $imported[] = $section;
                if ($section !== 'appearance') {
                    $counts[$section] = $sectionCount;
                }
            }
            if ($section === 'appearance' && isset($settingsPayload['branding'])) {
                $warnings[] = 'Apariencia importada con URLs de logo. Si las rutas no existen en destino, vuelve a subir los ficheros.';
            }
            if (\in_array($section, ['ai', 'mail', 'ingest', 'weather', 'markets', 'horoscope'], true) && !$includeSecrets) {
                // aviso genérico solo si el archivo tenía secretos omitidos en export
            }
        }

        if ($settingsEntries !== [] && !$dryRun) {
            $this->settingsWriter->setMany($site, $settingsEntries);
        }
        if ($settingsEntries !== [] && $dryRun) {
            $counts['_settings_keys'] = \count($settingsEntries);
        }

        $entityImporters = [
            'links' => fn () => $this->importLinks($em, $payload['sections']['links'] ?? [], $dryRun),
            'ads' => fn () => $this->importAds($em, $payload['sections']['ads'] ?? [], $dryRun),
            'polls' => fn () => $this->importPolls($em, $payload['sections']['polls'] ?? [], $dryRun),
            'galleries' => fn () => $this->importGalleries($em, $payload['sections']['galleries'] ?? [], $dryRun),
            'tags' => fn () => $this->importTags($em, $payload['sections']['tags'] ?? [], $dryRun),
            'webcams' => fn () => $this->importWebcams($em, $payload['sections']['webcams'] ?? [], $dryRun),
            'forum_categories' => fn () => $this->importForumCategories($em, $payload['sections']['forum_categories'] ?? [], $dryRun),
            'classified_categories' => fn () => $this->importClassifiedCategories($em, $payload['sections']['classified_categories'] ?? [], $dryRun),
        ];

        foreach ($entityImporters as $section => $importer) {
            if (!\in_array($section, $sections, true)) {
                continue;
            }
            $counts[$section] = $importer();
            $imported[] = $section;
            if ($section === 'polls') {
                $warnings[] = 'Encuestas importadas sin votos previos.';
            }
            if ($section === 'ads') {
                $warnings[] = 'Banners sustituidos por completo; los contadores de clics quedan a cero.';
            }
            if ($section === 'galleries') {
                $warnings[] = 'Galerías sustituidas; solo se importan URLs (no se copian ficheros).';
            }
            if ($section === 'links') {
                $warnings[] = 'Enlaces sustituidos por el conjunto del archivo.';
            }
            if ($section === 'webcams') {
                $warnings[] = 'Webcams sustituidas por el conjunto del archivo.';
            }
        }

        if ($dryRun && $em->isOpen()) {
            // No debería haber writes; por si acaso no hacemos flush.
        }

        return [
            'imported' => array_values(array_unique($imported)),
            'counts' => $counts,
            'warnings' => array_values(array_unique($warnings)),
            'dryRun' => $dryRun,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function assertPayload(array $payload): void
    {
        if (($payload['format'] ?? null) !== 'mymediacms-site-config') {
            throw new \InvalidArgumentException('El archivo no es una exportación de MyMediaCMS válida.');
        }
        $version = (int) ($payload['version'] ?? 0);
        if ($version < 1 || $version > self::VERSION) {
            throw new \InvalidArgumentException('Versión de exportación no compatible ('.$version.').');
        }
        if (!isset($payload['sections']) || !\is_array($payload['sections'])) {
            throw new \InvalidArgumentException('El archivo no contiene la sección «sections».');
        }
    }

    /**
     * @param list<string>|null $sections
     *
     * @return list<string>
     */
    public function normalizeSections(?array $sections): array
    {
        $allowed = self::allSections();
        if ($sections === null || $sections === []) {
            return $allowed;
        }

        return array_values(array_intersect($allowed, array_map('strval', $sections)));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function payloadIncludesCaches(array $payload): bool
    {
        return (bool) ($payload['options']['includeCaches'] ?? false);
    }

    /**
     * @param array<int, Category> $categoriesById
     * @param list<string>         $sections
     *
     * @return array{0: array<string, array<string, mixed>>, 1: list<string>, 2: list<string>}
     */
    private function exportSettings(
        EntityManagerInterface $em,
        array $categoriesById,
        array $sections,
        bool $includeSecrets,
        bool $includeCaches,
    ): array {
        $wantedNamespaces = [];
        foreach (self::SECTION_NAMESPACES as $section => $namespaces) {
            if (\in_array($section, $sections, true)) {
                foreach ($namespaces as $ns) {
                    $wantedNamespaces[$ns] = true;
                }
            }
        }

        $out = [];
        foreach (array_keys($wantedNamespaces) as $ns) {
            $out[$ns] = [];
        }

        $omittedSecrets = [];
        $omittedCaches = [];

        /** @var list<SiteSetting> $settings */
        $settings = $em->getRepository(SiteSetting::class)->findAll();
        foreach ($settings as $setting) {
            $ns = $setting->getNamespace();
            if (!isset($wantedNamespaces[$ns])) {
                continue;
            }
            $key = $setting->getKeyName();
            $fullKey = $ns.'.'.$key;
            if (\in_array($fullKey, self::SECRET_KEYS, true) && !$includeSecrets) {
                $omittedSecrets[] = $fullKey;
                continue;
            }
            if (\in_array($fullKey, self::CACHE_KEYS, true) && !$includeCaches) {
                $omittedCaches[] = $fullKey;
                continue;
            }
            $out[$ns][$key] = $setting->getValue()['value'] ?? null;
        }

        if (isset($out['navigation'])) {
            $out['navigation'] = $this->exportNavigation($out['navigation'], $categoriesById);
        }

        return [$out, array_values(array_unique($omittedSecrets)), array_values(array_unique($omittedCaches))];
    }

    /**
     * @param array<int, Category> $categoriesById
     *
     * @return list<array<string, mixed>>
     */
    private function exportCategories(array $categoriesById): array
    {
        $rows = [];
        foreach ($categoriesById as $category) {
            $rows[] = [
                'slug' => $category->getSlug(),
                'name' => $category->getName(),
                'description' => $category->getDescription(),
                'parentSlug' => $category->getParent()?->getSlug(),
                'sortOrder' => $category->getSortOrder(),
                'showInNav' => $category->isShowInNav(),
            ];
        }

        $bySlug = [];
        foreach ($rows as $row) {
            $bySlug[$row['slug']] = $row;
        }
        $depth = static function (string $slug) use (&$depth, $bySlug): int {
            $parent = $bySlug[$slug]['parentSlug'] ?? null;
            if ($parent === null || $parent === '' || !isset($bySlug[$parent])) {
                return 0;
            }

            return 1 + $depth($parent);
        };
        usort($rows, static function (array $a, array $b) use ($depth): int {
            $cmp = $depth($a['slug']) <=> $depth($b['slug']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return ($a['sortOrder'] <=> $b['sortOrder']) ?: strcmp((string) $a['slug'], (string) $b['slug']);
        });

        return $rows;
    }

    /**
     * @param array<string, mixed> $navigation
     * @param array<int, Category> $categoriesById
     *
     * @return array<string, mixed>
     */
    private function exportNavigation(array $navigation, array $categoriesById): array
    {
        $keys = ['main_json', 'footer_sections_json', 'footer_legal_json'];
        $result = [];
        foreach ($keys as $key) {
            $raw = $navigation[$key] ?? '[]';
            $items = [];
            if (\is_string($raw) && $raw !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    $items = \is_array($decoded) ? $decoded : [];
                } catch (\JsonException) {
                    $items = [];
                }
            } elseif (\is_array($raw)) {
                $items = $raw;
            }

            $mapped = [];
            foreach ($items as $item) {
                if (!\is_array($item)) {
                    continue;
                }
                $categoryId = $item['categoryId'] ?? null;
                $categorySlug = null;
                if ($categoryId !== null && $categoryId !== '' && isset($categoriesById[(int) $categoryId])) {
                    $categorySlug = $categoriesById[(int) $categoryId]->getSlug();
                }
                unset($item['categoryId']);
                $item['categorySlug'] = $categorySlug;
                $mapped[] = $item;
            }
            $result[$key] = $mapped;
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportLinks(EntityManagerInterface $em): array
    {
        $repo = $em->getRepository(SiteLink::class);
        if (!$repo instanceof SiteLinkRepository) {
            return [];
        }

        $rows = [];
        foreach ($repo->findBy([], ['group' => 'ASC', 'sortOrder' => 'ASC', 'id' => 'ASC']) as $link) {
            $rows[] = [
                'title' => $link->getTitle(),
                'url' => $link->getUrl(),
                'group' => $link->getGroup()->value,
                'logoUrl' => $link->getLogoUrl(),
                'sortOrder' => $link->getSortOrder(),
                'isEnabled' => $link->isEnabled(),
                'showInFooter' => $link->isShowInFooter(),
                'showInSidebar' => $link->isShowInSidebar(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportAds(EntityManagerInterface $em): array
    {
        $rows = [];
        foreach ($em->getRepository(AdBanner::class)->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']) as $ad) {
            $rows[] = [
                'name' => $ad->getName(),
                'placement' => $ad->getPlacement()->value,
                'placements' => $ad->getPlacementValues(),
                'categorySlugs' => $this->adCategorySlugs($em, $ad),
                'tagSlugs' => $this->adTagSlugs($em, $ad),
                'format' => $ad->getFormat()->value,
                'creativeType' => $ad->getCreativeType()->value,
                'imageUrl' => $ad->getImageUrl(),
                'linkUrl' => $ad->getLinkUrl(),
                'htmlContent' => $ad->getHtmlContent(),
                'altText' => $ad->getAltText(),
                'headline' => $ad->getHeadline(),
                'bodyText' => $ad->getBodyText(),
                'ctaLabel' => $ad->getCtaLabel(),
                'bgColor' => $ad->getBgColor(),
                'textColor' => $ad->getTextColor(),
                'accentColor' => $ad->getAccentColor(),
                'presetKey' => $ad->getPresetKey(),
                'isActive' => $ad->isActive(),
                'sortOrder' => $ad->getSortOrder(),
                'startsAt' => $ad->getStartsAt()?->format(\DateTimeInterface::ATOM),
                'endsAt' => $ad->getEndsAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportPolls(EntityManagerInterface $em): array
    {
        $rows = [];
        foreach ($em->getRepository(Poll::class)->findBy([], ['id' => 'ASC']) as $poll) {
            $options = [];
            foreach ($poll->getOptions() as $option) {
                $options[] = [
                    'label' => $option->getLabel(),
                    'sortOrder' => $option->getSortOrder(),
                ];
            }
            $rows[] = [
                'question' => $poll->getQuestion(),
                'isOpen' => $poll->getIsOpenFlag(),
                'showResultsBeforeVote' => $poll->isShowResultsBeforeVote(),
                'closesAt' => $poll->getClosesAt()?->format(\DateTimeInterface::ATOM),
                'options' => $options,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportGalleries(EntityManagerInterface $em): array
    {
        $rows = [];
        foreach ($em->getRepository(Gallery::class)->findBy([], ['id' => 'ASC']) as $gallery) {
            $images = [];
            foreach ($gallery->getImages() as $image) {
                $images[] = [
                    'url' => $image->getUrl(),
                    'alt' => $image->getAlt(),
                    'caption' => $image->getCaption(),
                    'sortOrder' => $image->getSortOrder(),
                ];
            }
            $rows[] = [
                'title' => $gallery->getTitle(),
                'images' => $images,
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportTags(EntityManagerInterface $em): array
    {
        $rows = [];
        foreach ($em->getRepository(ArticleTag::class)->findBy([], ['name' => 'ASC']) as $tag) {
            $rows[] = [
                'slug' => $tag->getSlug(),
                'name' => $tag->getName(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportWebcams(EntityManagerInterface $em): array
    {
        $rows = [];
        $repo = $em->getRepository(WeatherWebcam::class);
        $items = method_exists($repo, 'findAllOrdered')
            ? $repo->findAllOrdered()
            : $repo->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']);

        foreach ($items as $cam) {
            $rows[] = [
                'title' => $cam->getTitle(),
                'url' => $cam->getUrl(),
                'type' => $cam->getType(),
                'sortOrder' => $cam->getSortOrder(),
                'enabled' => $cam->isEnabled(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportForumCategories(EntityManagerInterface $em): array
    {
        $rows = [];
        foreach ($em->getRepository(ForumCategory::class)->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']) as $cat) {
            $rows[] = [
                'slug' => $cat->getSlug(),
                'name' => $cat->getName(),
                'description' => $cat->getDescription(),
                'sortOrder' => $cat->getSortOrder(),
                'isActive' => $cat->isActive(),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportClassifiedCategories(EntityManagerInterface $em): array
    {
        $rows = [];
        foreach ($em->getRepository(ClassifiedCategory::class)->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']) as $cat) {
            $rows[] = [
                'slug' => $cat->getSlug(),
                'name' => $cat->getName(),
                'description' => $cat->getDescription(),
                'sortOrder' => $cat->getSortOrder(),
                'isActive' => $cat->isActive(),
            ];
        }

        return $rows;
    }

    /**
     * @param mixed $rows
     */
    private function importCategories(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $repo = $em->getRepository(Category::class);
        if (!$repo instanceof CategoryRepository) {
            throw new \RuntimeException('CategoryRepository no disponible.');
        }

        $touched = [];
        for ($pass = 0; $pass < 3; ++$pass) {
            foreach ($rows as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                $slug = trim((string) ($row['slug'] ?? ''));
                $name = trim((string) ($row['name'] ?? ''));
                if ($slug === '' || $name === '') {
                    continue;
                }

                $parentSlug = $row['parentSlug'] ?? null;
                $parentSlug = \is_string($parentSlug) && $parentSlug !== '' ? $parentSlug : null;
                $parent = null;
                if ($parentSlug !== null) {
                    $parent = $repo->findOneBy(['slug' => $parentSlug]);
                    if ($parent === null) {
                        continue;
                    }
                }

                if ($dryRun) {
                    $touched[$slug] = true;
                    continue;
                }

                $category = $repo->findOneBy(['slug' => $slug]);
                if ($category === null) {
                    $category = new Category($name, $slug);
                    $em->persist($category);
                } else {
                    $category->setName($name);
                }

                $category->setDescription(
                    isset($row['description']) && \is_string($row['description']) && $row['description'] !== ''
                        ? $row['description']
                        : null,
                );
                $category->setParent($parent);
                $category->setSortOrder((int) ($row['sortOrder'] ?? 0));
                $category->setShowInNav((bool) ($row['showInNav'] ?? true));
                $touched[$slug] = true;
            }
            if (!$dryRun) {
                $em->flush();
            }
        }

        return \count($touched);
    }

    /**
     * @param array<string, mixed>    $navigation
     * @param array<string, Category> $categoriesBySlug
     *
     * @return array{0: list<array{0: string, 1: string, 2: mixed}>, 1: list<string>}
     */
    private function prepareNavigationImport(array $navigation, array $categoriesBySlug): array
    {
        $normalizer = new NavMenuNormalizer();
        $warnings = [];
        $menus = [];

        foreach (['main_json' => 'main', 'footer_sections_json' => 'footerSections', 'footer_legal_json' => 'footerLegal'] as $key => $label) {
            $items = $navigation[$key] ?? [];
            if (!\is_array($items)) {
                $items = [];
            }
            $resolved = [];
            foreach ($items as $item) {
                if (!\is_array($item)) {
                    continue;
                }
                $slug = $item['categorySlug'] ?? null;
                unset($item['categorySlug']);
                if (\is_string($slug) && $slug !== '') {
                    if (isset($categoriesBySlug[$slug])) {
                        $item['categoryId'] = $categoriesBySlug[$slug]->getId();
                    } else {
                        $item['categoryId'] = null;
                        $warnings[] = sprintf('Menú «%s»: categoría «%s» no encontrada; se omitió el enlace a categoría.', $label, $slug);
                    }
                } else {
                    $item['categoryId'] = $item['categoryId'] ?? null;
                }
                $resolved[] = $item;
            }
            $menus[$key] = $resolved;
        }

        $config = $normalizer->toConfig(
            $menus['main_json'],
            $menus['footer_sections_json'],
            $menus['footer_legal_json'],
        );

        return [[
            ['navigation', 'main_json', json_encode($config->mainItems, JSON_THROW_ON_ERROR)],
            ['navigation', 'footer_sections_json', json_encode($config->footerSectionItems, JSON_THROW_ON_ERROR)],
            ['navigation', 'footer_legal_json', json_encode($config->footerLegalItems, JSON_THROW_ON_ERROR)],
        ], $warnings];
    }

    /**
     * @param mixed $rows
     */
    private function importLinks(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $valid = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $group = SiteLinkGroup::tryFrom((string) ($row['group'] ?? ''));
            if ($title === '' || $url === '' || $group === null) {
                continue;
            }
            ++$valid;
            if ($dryRun) {
                continue;
            }
        }

        if ($dryRun) {
            return $valid;
        }

        $repo = $em->getRepository(SiteLink::class);
        if (!$repo instanceof SiteLinkRepository) {
            return 0;
        }

        foreach ($repo->findAll() as $existing) {
            $em->remove($existing);
        }

        $count = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            $group = SiteLinkGroup::tryFrom((string) ($row['group'] ?? ''));
            if ($title === '' || $url === '' || $group === null) {
                continue;
            }
            $link = new SiteLink($title, $url, $group);
            $logo = $row['logoUrl'] ?? null;
            $link->setLogoUrl(\is_string($logo) && $logo !== '' ? $logo : null);
            $link->setSortOrder((int) ($row['sortOrder'] ?? 0));
            $link->setIsEnabled((bool) ($row['isEnabled'] ?? true));
            $link->setShowInFooter((bool) ($row['showInFooter'] ?? true));
            $link->setShowInSidebar((bool) ($row['showInSidebar'] ?? false));
            $em->persist($link);
            ++$count;
        }
        $em->flush();

        return $count;
    }

    /**
     * @param mixed $rows
     */
    private function importAds(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $valid = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $placements = $this->parseAdPlacements($row);
            $format = AdFormat::tryFrom((string) ($row['format'] ?? ''));
            if ($name === '' || $placements === [] || $format === null) {
                continue;
            }
            ++$valid;
        }
        if ($dryRun) {
            return $valid;
        }

        foreach ($em->getRepository(AdBanner::class)->findAll() as $existing) {
            $em->remove($existing);
        }
        $em->flush();

        $count = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $placements = $this->parseAdPlacements($row);
            $format = AdFormat::tryFrom((string) ($row['format'] ?? ''));
            if ($name === '' || $placements === [] || $format === null) {
                continue;
            }
            $ad = new AdBanner($name, $placements[0], $format);
            $ad->setPlacements($placements);
            $ad->setCategoryIds($this->resolveAdCategoryIds($em, $row));
            $ad->setTagIds($this->resolveAdTagIds($em, $row));
            $creative = AdCreativeType::tryFrom((string) ($row['creativeType'] ?? 'image')) ?? AdCreativeType::Image;
            $ad->setCreativeType($creative);
            $ad->setImageUrl($this->nullableString($row['imageUrl'] ?? null));
            $ad->setLinkUrl($this->nullableString($row['linkUrl'] ?? null));
            $ad->setHtmlContent($this->nullableString($row['htmlContent'] ?? null));
            $ad->setAltText($this->nullableString($row['altText'] ?? null));
            $ad->setHeadline($this->nullableString($row['headline'] ?? null));
            $ad->setBodyText($this->nullableString($row['bodyText'] ?? null));
            $ad->setCtaLabel($this->nullableString($row['ctaLabel'] ?? null));
            $ad->setBgColor($this->nullableString($row['bgColor'] ?? null));
            $ad->setTextColor($this->nullableString($row['textColor'] ?? null));
            $ad->setAccentColor($this->nullableString($row['accentColor'] ?? null));
            $ad->setPresetKey($this->nullableString($row['presetKey'] ?? null));
            $ad->setIsActive((bool) ($row['isActive'] ?? true));
            $ad->setSortOrder((int) ($row['sortOrder'] ?? 0));
            $ad->setStartsAt($this->parseDate($row['startsAt'] ?? null));
            $ad->setEndsAt($this->parseDate($row['endsAt'] ?? null));
            $em->persist($ad);
            ++$count;
        }
        $em->flush();

        return $count;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<AdPlacement>
     */
    private function parseAdPlacements(array $row): array
    {
        $placements = [];
        $rawList = $row['placements'] ?? null;
        if (\is_array($rawList)) {
            foreach ($rawList as $raw) {
                $placement = AdPlacement::tryFrom((string) $raw);
                if ($placement !== null) {
                    $placements[$placement->value] = $placement;
                }
            }
        }
        if ($placements === []) {
            $placement = AdPlacement::tryFrom((string) ($row['placement'] ?? ''));
            if ($placement !== null) {
                $placements[$placement->value] = $placement;
            }
        }

        return array_values($placements);
    }

    /**
     * @return list<string>
     */
    private function adCategorySlugs(EntityManagerInterface $em, AdBanner $ad): array
    {
        $slugs = [];
        foreach ($ad->getCategoryIds() as $id) {
            $category = $em->find(Category::class, $id);
            if ($category instanceof Category) {
                $slugs[] = $category->getSlug();
            }
        }

        return $slugs;
    }

    /**
     * @return list<string>
     */
    private function adTagSlugs(EntityManagerInterface $em, AdBanner $ad): array
    {
        $slugs = [];
        foreach ($ad->getTagIds() as $id) {
            $tag = $em->find(ArticleTag::class, $id);
            if ($tag instanceof ArticleTag) {
                $slugs[] = $tag->getSlug();
            }
        }

        return $slugs;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<int>
     */
    private function resolveAdCategoryIds(EntityManagerInterface $em, array $row): array
    {
        $ids = [];
        $rawSlugs = $row['categorySlugs'] ?? [];
        if (\is_array($rawSlugs)) {
            foreach ($rawSlugs as $slug) {
                $slug = trim((string) $slug);
                if ($slug === '') {
                    continue;
                }
                $category = $em->getRepository(Category::class)->findOneBy(['slug' => $slug]);
                if ($category instanceof Category && $category->getId() !== null) {
                    $ids[$category->getId()] = $category->getId();
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<int>
     */
    private function resolveAdTagIds(EntityManagerInterface $em, array $row): array
    {
        $ids = [];
        $rawSlugs = $row['tagSlugs'] ?? [];
        if (\is_array($rawSlugs)) {
            foreach ($rawSlugs as $slug) {
                $slug = trim((string) $slug);
                if ($slug === '') {
                    continue;
                }
                $tag = $em->getRepository(ArticleTag::class)->findOneBy(['slug' => $slug]);
                if ($tag instanceof ArticleTag && $tag->getId() !== null) {
                    $ids[$tag->getId()] = $tag->getId();
                }
            }
        }

        return array_values($ids);
    }

    /**
     * @param mixed $rows
     */
    private function importPolls(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $valid = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            if (trim((string) ($row['question'] ?? '')) === '') {
                continue;
            }
            ++$valid;
        }
        if ($dryRun) {
            return $valid;
        }

        foreach ($em->getRepository(Poll::class)->findAll() as $existing) {
            $em->remove($existing);
        }
        $em->flush();

        $count = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $question = trim((string) ($row['question'] ?? ''));
            if ($question === '') {
                continue;
            }
            $poll = new Poll($question);
            $poll->setIsOpen((bool) ($row['isOpen'] ?? true));
            $poll->setShowResultsBeforeVote((bool) ($row['showResultsBeforeVote'] ?? false));
            $poll->setClosesAt($this->parseDate($row['closesAt'] ?? null));
            $options = $row['options'] ?? [];
            if (\is_array($options)) {
                $i = 0;
                foreach ($options as $opt) {
                    if (!\is_array($opt)) {
                        continue;
                    }
                    $label = trim((string) ($opt['label'] ?? ''));
                    if ($label === '') {
                        continue;
                    }
                    $poll->addOption(new PollOption($label, (int) ($opt['sortOrder'] ?? $i)));
                    ++$i;
                }
            }
            $em->persist($poll);
            ++$count;
        }
        $em->flush();

        return $count;
    }

    /**
     * @param mixed $rows
     */
    private function importGalleries(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $valid = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            if (trim((string) ($row['title'] ?? '')) === '') {
                continue;
            }
            ++$valid;
        }
        if ($dryRun) {
            return $valid;
        }

        foreach ($em->getRepository(Gallery::class)->findAll() as $existing) {
            $em->remove($existing);
        }
        $em->flush();

        $count = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') {
                continue;
            }
            $gallery = new Gallery($title);
            $images = $row['images'] ?? [];
            if (\is_array($images)) {
                $i = 0;
                foreach ($images as $img) {
                    if (!\is_array($img)) {
                        continue;
                    }
                    $url = trim((string) ($img['url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }
                    $image = new GalleryImage($gallery, $url, (int) ($img['sortOrder'] ?? $i));
                    $image->setAlt($this->nullableString($img['alt'] ?? null));
                    $image->setCaption($this->nullableString($img['caption'] ?? null));
                    $gallery->addImage($image);
                    ++$i;
                }
            }
            $em->persist($gallery);
            ++$count;
        }
        $em->flush();

        return $count;
    }

    /**
     * @param mixed $rows
     */
    private function importTags(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $repo = $em->getRepository(ArticleTag::class);
        $count = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($slug === '' || $name === '') {
                continue;
            }
            ++$count;
            if ($dryRun) {
                continue;
            }
            $tag = $repo->findOneBy(['slug' => $slug]);
            if ($tag === null) {
                $em->persist(new ArticleTag($name, $slug));
            } else {
                $tag->setName($name);
            }
        }
        if (!$dryRun) {
            $em->flush();
        }

        return $count;
    }

    /**
     * @param mixed $rows
     */
    private function importWebcams(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $valid = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }
            ++$valid;
        }
        if ($dryRun) {
            return $valid;
        }

        foreach ($em->getRepository(WeatherWebcam::class)->findAll() as $existing) {
            $em->remove($existing);
        }
        $em->flush();

        $count = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($title === '' || $url === '') {
                continue;
            }
            $type = trim((string) ($row['type'] ?? 'embed')) ?: 'embed';
            $cam = new WeatherWebcam($title, $url, $type);
            $cam->setSortOrder((int) ($row['sortOrder'] ?? 0));
            $cam->setEnabled((bool) ($row['enabled'] ?? true));
            $em->persist($cam);
            ++$count;
        }
        $em->flush();

        return $count;
    }

    /**
     * @param mixed $rows
     */
    private function importForumCategories(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $repo = $em->getRepository(ForumCategory::class);
        $count = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($slug === '' || $name === '') {
                continue;
            }
            ++$count;
            if ($dryRun) {
                continue;
            }
            $cat = $repo->findOneBy(['slug' => $slug]);
            if ($cat === null) {
                $cat = new ForumCategory($name, $slug);
                $em->persist($cat);
            } else {
                $cat->setName($name);
            }
            $cat->setDescription($this->nullableString($row['description'] ?? null));
            $cat->setSortOrder((int) ($row['sortOrder'] ?? 0));
            $cat->setActive((bool) ($row['isActive'] ?? true));
        }
        if (!$dryRun) {
            $em->flush();
        }

        return $count;
    }

    /**
     * @param mixed $rows
     */
    private function importClassifiedCategories(EntityManagerInterface $em, mixed $rows, bool $dryRun): int
    {
        if (!\is_array($rows)) {
            return 0;
        }

        $repo = $em->getRepository(ClassifiedCategory::class);
        $count = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            if ($slug === '' || $name === '') {
                continue;
            }
            ++$count;
            if ($dryRun) {
                continue;
            }
            $cat = $repo->findOneBy(['slug' => $slug]);
            if ($cat === null) {
                $cat = new ClassifiedCategory($name, $slug);
                $em->persist($cat);
            } else {
                $cat->setName($name);
            }
            $cat->setDescription($this->nullableString($row['description'] ?? null));
            $cat->setSortOrder((int) ($row['sortOrder'] ?? 0));
            $cat->setActive((bool) ($row['isActive'] ?? true));
        }
        if (!$dryRun) {
            $em->flush();
        }

        return $count;
    }

    private function nullableString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!\is_string($value) || trim($value) === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<int, Category>
     */
    private function loadCategoriesIndexed(EntityManagerInterface $em): array
    {
        $indexed = [];
        foreach ($em->getRepository(Category::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']) as $category) {
            if ($category->getId() !== null) {
                $indexed[$category->getId()] = $category;
            }
        }

        return $indexed;
    }

    /**
     * @return array<string, Category>
     */
    private function loadCategoriesBySlug(EntityManagerInterface $em): array
    {
        $indexed = [];
        foreach ($em->getRepository(Category::class)->findAll() as $category) {
            $indexed[$category->getSlug()] = $category;
        }

        return $indexed;
    }
}
