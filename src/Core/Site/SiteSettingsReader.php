<?php

namespace App\Core\Site;

use App\Core\Site\Dto\AnalyticsConfig;
use App\Core\Site\Dto\AiConfig;
use App\Core\Site\Dto\ContentModulesConfig;
use App\Core\Site\Dto\HimtecMediaIngestConfig;
use App\Core\Site\Dto\SiteAppearanceConfig;
use App\Core\Site\Dto\SiteLayoutConfig;
use App\Core\Site\Dto\SiteSeoConfig;
use App\Core\Site\Dto\ClassifiedsConfig;
use App\Core\Site\Dto\ForumConfig;
use App\Core\Site\Dto\GamesConfig;
use App\Core\Site\Dto\HoroscopeConfig;
use App\Core\Site\Dto\TvConfig;
use App\Entity\Site\Enum\ArticleLayoutTemplate;
use App\Entity\Site\Enum\CategoryLayoutTemplate;
use App\Entity\Site\Enum\HomeLayoutTemplate;
use App\Entity\Site\SiteSetting;
use App\Editorial\SiteFontRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SiteSettingsReader
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
        #[Autowire(env: 'string:default::OPENAI_API_KEY')]
        private readonly string $defaultOpenaiApiKey = '',
    ) {}

    public function getAppearance(SiteContext $context): SiteAppearanceConfig
    {
        $defaults = new SiteAppearanceConfig();

        $data = $defaults->toArray();

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? '';
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'theme.primary_color' => $data['primaryColor'] = (string) $value,
                'theme.secondary_color' => $data['secondaryColor'] = (string) $value,
                'theme.background_color' => $data['backgroundColor'] = (string) $value,
                'theme.surface_color' => $data['surfaceColor'] = (string) $value,
                'theme.text_color' => $data['textColor'] = (string) $value,
                'theme.text_muted_color' => $data['textMutedColor'] = (string) $value,
                'theme.heading_color' => $data['headingColor'] = (string) $value,
                'theme.link_color' => $data['linkColor'] = (string) $value,
                'theme.border_color' => $data['borderColor'] = (string) $value,
                'theme.top_bar_color' => $data['topBarColor'] = (string) $value,
                'theme.nav_color' => $data['navColor'] = (string) $value,
                'theme.nav_bar_color' => $data['navBarColor'] = (string) $value,
                'theme.footer_color' => $data['footerColor'] = (string) $value,
                'theme.heading_font' => $data['headingFont'] = (string) $value,
                'theme.body_font' => $data['bodyFont'] = (string) $value,
                'theme.article_font' => $data['articleFont'] = (string) $value,
                'branding.logo_url' => $data['logoUrl'] = (string) $value,
                'branding.logo_dark_url' => $data['logoDarkUrl'] = (string) $value,
                'branding.logo_mark_url' => $data['logoMarkUrl'] = (string) $value,
                'branding.favicon_url' => $data['faviconUrl'] = (string) $value,
                'identity.tagline' => $data['tagline'] = (string) $value,
                'seo.meta_description' => $data['metaDescription'] = (string) $value,
                'seo.meta_keywords' => $data['metaKeywords'] = (string) $value,
                default => null,
            };
        }

        return new SiteAppearanceConfig(
            tagline: $data['tagline'],
            metaDescription: $data['metaDescription'],
            metaKeywords: $data['metaKeywords'],
            primaryColor: $data['primaryColor'],
            secondaryColor: $data['secondaryColor'],
            backgroundColor: $data['backgroundColor'],
            surfaceColor: $data['surfaceColor'],
            textColor: $data['textColor'],
            textMutedColor: $data['textMutedColor'],
            headingColor: $data['headingColor'],
            linkColor: $data['linkColor'],
            borderColor: $data['borderColor'],
            topBarColor: $data['topBarColor'],
            navColor: $data['navColor'],
            navBarColor: $data['navBarColor'],
            footerColor: $data['footerColor'],
            headingFont: SiteFontRegistry::isValid($data['headingFont']) ? $data['headingFont'] : SiteFontRegistry::DEFAULT_HEADING_FONT,
            bodyFont: SiteFontRegistry::isValid($data['bodyFont']) ? $data['bodyFont'] : SiteFontRegistry::DEFAULT_BODY_FONT,
            articleFont: SiteFontRegistry::isValid($data['articleFont']) ? $data['articleFont'] : SiteFontRegistry::DEFAULT_ARTICLE_FONT,
            logoUrl: $data['logoUrl'],
            logoDarkUrl: $data['logoDarkUrl'],
            logoMarkUrl: $data['logoMarkUrl'],
            faviconUrl: $data['faviconUrl'],
        );
    }

    public function getFooterConfig(SiteContext $context): \App\Core\Site\Dto\SiteFooterConfig
    {
        $data = [
            'contactEmail' => '',
            'contactPhone' => '',
            'contactAddress' => '',
            'facebookUrl' => '',
            'twitterUrl' => '',
            'instagramUrl' => '',
            'youtubeUrl' => '',
            'linkedinUrl' => '',
            'whatsappUrl' => '',
            'tiktokUrl' => '',
            'telegramUrl' => '',
        ];

        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace() !== 'footer') {
                continue;
            }
            $raw = $setting->getValue()['value'] ?? '';
            $value = is_scalar($raw) ? (string) $raw : '';
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'footer.contact_email' => $data['contactEmail'] = $value,
                'footer.contact_phone' => $data['contactPhone'] = $value,
                'footer.contact_address' => $data['contactAddress'] = $value,
                'footer.facebook_url' => $data['facebookUrl'] = $value,
                'footer.twitter_url' => $data['twitterUrl'] = $value,
                'footer.instagram_url' => $data['instagramUrl'] = $value,
                'footer.youtube_url' => $data['youtubeUrl'] = $value,
                'footer.linkedin_url' => $data['linkedinUrl'] = $value,
                'footer.whatsapp_url' => $data['whatsappUrl'] = $value,
                'footer.tiktok_url' => $data['tiktokUrl'] = $value,
                'footer.telegram_url' => $data['telegramUrl'] = $value,
                default => null,
            };
        }

        return new \App\Core\Site\Dto\SiteFooterConfig(
            contactEmail: $data['contactEmail'],
            contactPhone: $data['contactPhone'],
            contactAddress: $data['contactAddress'],
            facebookUrl: $data['facebookUrl'],
            twitterUrl: $data['twitterUrl'],
            instagramUrl: $data['instagramUrl'],
            youtubeUrl: $data['youtubeUrl'],
            linkedinUrl: $data['linkedinUrl'],
            whatsappUrl: $data['whatsappUrl'],
            tiktokUrl: $data['tiktokUrl'],
            telegramUrl: $data['telegramUrl'],
        );
    }

    public function getMailConfig(SiteContext $context): \App\Core\Site\Dto\SiteMailConfig
    {
        $data = [
            'enabled' => false,
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'fromEmail' => '',
            'fromName' => '',
            'contactRecipient' => '',
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? '';
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'mail.smtp_enabled' => $data['enabled'] = filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'mail.smtp_host' => $data['host'] = (string) $value,
                'mail.smtp_port' => $data['port'] = max(1, (int) $value),
                'mail.smtp_encryption' => $data['encryption'] = (string) $value,
                'mail.smtp_username' => $data['username'] = (string) $value,
                'mail.smtp_password' => $data['password'] = (string) $value,
                'mail.from_email' => $data['fromEmail'] = (string) $value,
                'mail.from_name' => $data['fromName'] = (string) $value,
                'mail.contact_recipient' => $data['contactRecipient'] = (string) $value,
                default => null,
            };
        }

        if (!\in_array($data['encryption'], ['tls', 'ssl', 'none'], true)) {
            $data['encryption'] = 'tls';
        }

        $password = \App\Core\Site\Dto\SiteMailConfig::normalizeSmtpPassword($data['password']);

        return new \App\Core\Site\Dto\SiteMailConfig(
            enabled: $data['enabled'],
            host: $data['host'] !== '' ? $data['host'] : 'smtp.gmail.com',
            port: $data['port'],
            encryption: $data['encryption'],
            username: $data['username'],
            password: $password,
            fromEmail: $data['fromEmail'],
            fromName: $data['fromName'],
            contactRecipient: $data['contactRecipient'],
            hasPassword: $password !== '',
        );
    }

    /** @return array<string, string> */
    public function getPagesContent(SiteContext $context): array
    {
        $defaults = $this->defaultPagesContent();
        $content = $defaults;

        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace() !== 'pages') {
                continue;
            }
            $value = $setting->getValue()['value'] ?? '';
            if (is_string($value) && trim($value) !== '') {
                $content[$setting->getKeyName()] = $value;
            }
        }

        return $content;
    }

    public function getPageHtml(SiteContext $context, string $settingKey): string
    {
        $all = $this->getPagesContent($context);

        return $all[$settingKey] ?? '';
    }

    /** @return array<string, string> */
    private function defaultPagesContent(): array
    {
        return [
            'quienes_somos_html' => '<p>Somos un medio digital independiente dedicado a la información de calidad, el análisis y el servicio público a la audiencia.</p><p>Nuestro equipo de periodistas trabaja cada día para ofrecer cobertura rigurosa de la actualidad local, nacional e internacional.</p>',
            'aviso_legal_html' => '<p>En cumplimiento de la normativa aplicable, se informa a los usuarios de este sitio web de los datos identificativos del responsable del mismo.</p><p>El acceso y uso de este sitio implica la aceptación de las condiciones de uso. Los contenidos están protegidos por la legislación sobre propiedad intelectual.</p><p>Para cualquier consulta relacionada con este aviso legal, utilice el formulario de contacto.</p>',
            'privacidad_html' => '<p>Tratamos los datos personales que nos facilitas con la finalidad de gestionar tu consulta o solicitud y mantener la comunicación derivada del formulario de contacto.</p><p>La base legitimadora es tu consentimiento al enviar el formulario. Los datos se conservarán el tiempo necesario para atender tu solicitud y cumplir obligaciones legales.</p><p>Puedes ejercer tus derechos de acceso, rectificación, supresión y otros previstos en la normativa escribiendo a través del formulario de contacto.</p>',
            'cookies_html' => '<p>Este sitio web utiliza cookies propias y de terceros para mejorar la experiencia de navegación, analizar el tráfico y personalizar contenidos.</p><p>Puede configurar o rechazar las cookies no esenciales desde su navegador. Para más información sobre los tipos de cookies que utilizamos, contacte con nosotros.</p>',
            'contacto_html' => '<p>¿Tienes una información, una propuesta o una consulta? Escríbenos y te responderemos lo antes posible.</p><p>Para colaboraciones editoriales, envía tu mensaje indicando el tema y los datos de contacto.</p>',
            'anunciantes_html' => '<p>Si quieres anunciarte en este medio, contacta con nuestro equipo comercial a través del formulario de contacto o del correo indicado en el pie de página.</p>',
        ];
    }

    /** @return array<string, mixed> */
    public function getTheme(SiteContext $context): array
    {
        $appearance = $this->getAppearance($context);

        return array_merge($appearance->toArray(), [
            'headingFontFamily' => SiteFontRegistry::cssFamily($appearance->headingFont),
            'bodyFontFamily' => SiteFontRegistry::cssFamily($appearance->bodyFont),
            'articleFontFamily' => SiteFontRegistry::cssFamily($appearance->articleFont),
            'googleFontsUrl' => SiteFontRegistry::buildGoogleFontsUrl([
                $appearance->headingFont,
                $appearance->bodyFont,
                $appearance->articleFont,
            ]),
            'linkColorResolved' => $appearance->linkColorResolved(),
            'navColorResolved' => $appearance->navColorResolved(),
            'navBarColorResolved' => $appearance->navBarColorResolved(),
            'topBarFg' => $appearance->topBarFg(),
            'footerFg' => $appearance->footerFg(),
            'navBarFg' => $appearance->navBarFg(),
            'topBarTone' => $appearance->topBarTone(),
            'footerTone' => $appearance->footerTone(),
            'navBarTone' => $appearance->navBarTone(),
        ]);
    }

    public function getIngestConfig(SiteContext $context): HimtecMediaIngestConfig
    {
        $data = [
            'enabled' => false,
            'jsonUrl' => '',
            'apiKey' => '',
            'intervalMinutes' => 60,
            'lastFetchedAt' => null,
            'lastFetchStatus' => '',
            'lastFetchCount' => 0,
            'lastFetchDuplicates' => 0,
            'publishStaggerMinutes' => 0,
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'ingest.himtecmedia_enabled' => $data['enabled'] = (bool) $value,
                'ingest.himtecmedia_json_url' => $data['jsonUrl'] = (string) $value,
                'ingest.himtecmedia_api_key' => $data['apiKey'] = (string) $value,
                'ingest.himtecmedia_interval_minutes' => $data['intervalMinutes'] = max(5, (int) $value),
                'ingest.himtecmedia_last_fetched_at' => $data['lastFetchedAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                'ingest.himtecmedia_last_fetch_status' => $data['lastFetchStatus'] = (string) $value,
                'ingest.himtecmedia_last_fetch_count' => $data['lastFetchCount'] = (int) $value,
                'ingest.himtecmedia_last_fetch_duplicates' => $data['lastFetchDuplicates'] = (int) $value,
                'ingest.himtecmedia_publish_stagger_minutes' => $data['publishStaggerMinutes'] = max(0, (int) $value),
                default => null,
            };
        }

        return new HimtecMediaIngestConfig(
            enabled: $data['enabled'],
            jsonUrl: $data['jsonUrl'],
            apiKey: $data['apiKey'],
            intervalMinutes: $data['intervalMinutes'],
            lastFetchedAt: $data['lastFetchedAt'],
            lastFetchStatus: $data['lastFetchStatus'],
            lastFetchCount: $data['lastFetchCount'],
            lastFetchDuplicates: $data['lastFetchDuplicates'],
            publishStaggerMinutes: $data['publishStaggerMinutes'],
        );
    }

    public function getWeatherConfig(SiteContext $context): \App\Core\Site\Dto\WeatherConfig
    {
        $data = [
            'enabled' => false,
            'postalCode' => '',
            'placeName' => '',
            'latitude' => null,
            'longitude' => null,
            'cachedAt' => null,
            'cachedJson' => '',
            'sponsorHeadline' => 'El tiempo patrocinado por',
            'sponsorName' => '',
            'sponsorLogoUrl' => '',
            'sponsorLinkUrl' => '',
            'sponsorBannerUrl' => '',
            'sponsorHtml' => '',
            'sponsorShowInNav' => true,
            'aiSummaryEnabled' => false,
            'openaiApiKey' => '',
            'aiSummaryJson' => '',
            'aiSummaryCachedAt' => null,
            'aiSummaryForecastAt' => null,
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'weather.enabled' => $data['enabled'] = (bool) $value,
                'weather.postal_code' => $data['postalCode'] = (string) $value,
                'weather.place_name' => $data['placeName'] = (string) $value,
                'weather.latitude' => $data['latitude'] = is_numeric($value) ? (float) $value : null,
                'weather.longitude' => $data['longitude'] = is_numeric($value) ? (float) $value : null,
                'weather.cached_at' => $data['cachedAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                'weather.cached_json' => $data['cachedJson'] = (string) $value,
                'weather.sponsor_headline' => $data['sponsorHeadline'] = (string) $value,
                'weather.sponsor_name' => $data['sponsorName'] = (string) $value,
                'weather.sponsor_logo_url' => $data['sponsorLogoUrl'] = (string) $value,
                'weather.sponsor_link_url' => $data['sponsorLinkUrl'] = (string) $value,
                'weather.sponsor_banner_url' => $data['sponsorBannerUrl'] = (string) $value,
                'weather.sponsor_html' => $data['sponsorHtml'] = (string) $value,
                'weather.sponsor_show_in_nav' => $data['sponsorShowInNav'] = (bool) $value,
                'weather.ai_summary_enabled' => $data['aiSummaryEnabled'] = (bool) $value,
                'weather.openai_api_key' => $data['openaiApiKey'] = (string) $value,
                'weather.ai_summary_json' => $data['aiSummaryJson'] = (string) $value,
                'weather.ai_summary_cached_at' => $data['aiSummaryCachedAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                'weather.ai_summary_forecast_at' => $data['aiSummaryForecastAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                default => null,
            };
        }

        $sponsor = new \App\Core\Site\Dto\WeatherSponsorConfig(
            headline: $data['sponsorHeadline'] !== '' ? $data['sponsorHeadline'] : 'El tiempo patrocinado por',
            name: $data['sponsorName'],
            logoUrl: $data['sponsorLogoUrl'],
            linkUrl: $data['sponsorLinkUrl'],
            bannerUrl: $data['sponsorBannerUrl'],
            html: $data['sponsorHtml'],
            showInNav: $data['sponsorShowInNav'],
        );

        return new \App\Core\Site\Dto\WeatherConfig(
            enabled: $data['enabled'],
            postalCode: $data['postalCode'],
            placeName: $data['placeName'],
            latitude: $data['latitude'],
            longitude: $data['longitude'],
            cachedAt: $data['cachedAt'],
            cachedJson: $data['cachedJson'],
            sponsor: $sponsor,
            aiSummaryEnabled: $data['aiSummaryEnabled'],
            openaiApiKey: $data['openaiApiKey'],
            hasOpenaiApiKey: $data['openaiApiKey'] !== '',
            aiSummaryJson: $data['aiSummaryJson'],
            aiSummaryCachedAt: $data['aiSummaryCachedAt'],
            aiSummaryForecastAt: $data['aiSummaryForecastAt'],
        );
    }

    public function getAccommodationsConfig(SiteContext $context): \App\Core\Site\Dto\AccommodationsConfig
    {
        $weather = $this->getWeatherConfig($context);
        $enabled = false;
        $title = 'Alojamientos';
        $widgetHtml = '';

        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace() !== 'accommodations') {
                continue;
            }
            $raw = $setting->getValue()['value'] ?? '';
            match ($setting->getKeyName()) {
                'enabled' => $enabled = (bool) $raw,
                'title' => $title = is_scalar($raw) && trim((string) $raw) !== '' ? (string) $raw : 'Alojamientos',
                'widget_html' => $widgetHtml = is_scalar($raw) ? (string) $raw : '',
                default => null,
            };
        }

        return new \App\Core\Site\Dto\AccommodationsConfig(
            enabled: $enabled,
            title: $title,
            widgetHtml: $widgetHtml,
            postalCode: $weather->postalCode,
            placeName: $weather->placeName,
        );
    }

    public function getMarketConfig(SiteContext $context): \App\Core\Site\Dto\MarketConfig
    {
        $data = [
            'enabled' => false,
            'indexIds' => \App\Markets\MarketIndexRegistry::DEFAULT_INDEX_IDS,
            'cachedAt' => null,
            'cachedJson' => '',
            'aiSummaryEnabled' => false,
            'openaiApiKey' => '',
            'aiSummaryJson' => '',
            'aiSummaryCachedAt' => null,
            'aiSummaryBoardAt' => null,
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'markets.enabled' => $data['enabled'] = (bool) $value,
                'markets.index_ids' => $data['indexIds'] = $this->decodeMarketIndexIds($value),
                'markets.cached_at' => $data['cachedAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                'markets.cached_json' => $data['cachedJson'] = (string) $value,
                'markets.ai_summary_enabled' => $data['aiSummaryEnabled'] = (bool) $value,
                'markets.openai_api_key' => $data['openaiApiKey'] = (string) $value,
                'markets.ai_summary_json' => $data['aiSummaryJson'] = (string) $value,
                'markets.ai_summary_cached_at' => $data['aiSummaryCachedAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                'markets.ai_summary_board_at' => $data['aiSummaryBoardAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                default => null,
            };
        }

        return new \App\Core\Site\Dto\MarketConfig(
            enabled: $data['enabled'],
            indexIds: \App\Markets\MarketIndexRegistry::normalizeIds($data['indexIds']),
            cachedAt: $data['cachedAt'],
            cachedJson: $data['cachedJson'],
            aiSummaryEnabled: $data['aiSummaryEnabled'],
            openaiApiKey: $data['openaiApiKey'],
            hasOpenaiApiKey: $data['openaiApiKey'] !== '',
            aiSummaryJson: $data['aiSummaryJson'],
            aiSummaryCachedAt: $data['aiSummaryCachedAt'],
            aiSummaryBoardAt: $data['aiSummaryBoardAt'],
        );
    }

    public function getAnalyticsConfig(SiteContext $context): AnalyticsConfig
    {
        $data = [
            'enabled' => false,
            'headCode' => '',
            'bodyCode' => '',
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'analytics.enabled' => $data['enabled'] = (bool) $value,
                'analytics.head_code' => $data['headCode'] = (string) $value,
                'analytics.body_code' => $data['bodyCode'] = (string) $value,
                default => null,
            };
        }

        return new AnalyticsConfig(
            enabled: $data['enabled'],
            headCode: $data['headCode'],
            bodyCode: $data['bodyCode'],
        );
    }

    public function getSeoConfig(SiteContext $context): SiteSeoConfig
    {
        $defaults = new SiteSeoConfig();
        $data = $defaults->toArray();

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'seo.home_title' => $data['homeTitle'] = (string) $value,
                'seo.meta_description' => $data['metaDescription'] = (string) $value,
                'seo.meta_keywords' => $data['metaKeywords'] = (string) $value,
                'seo.title_separator' => $data['titleSeparator'] = (string) $value,
                'seo.default_robots' => $data['defaultRobots'] = (string) $value,
                'seo.default_og_image' => $data['defaultOgImage'] = (string) $value,
                'seo.theme_color' => $data['themeColor'] = (string) $value,
                'seo.og_site_name' => $data['ogSiteName'] = (string) $value,
                'seo.og_locale' => $data['ogLocale'] = (string) $value,
                'seo.twitter_site' => $data['twitterSite'] = (string) $value,
                'seo.facebook_app_id' => $data['facebookAppId'] = (string) $value,
                'seo.facebook_admins' => $data['facebookAdmins'] = (string) $value,
                'seo.google_site_verification' => $data['googleSiteVerification'] = (string) $value,
                'seo.bing_site_verification' => $data['bingSiteVerification'] = (string) $value,
                'seo.facebook_domain_verification' => $data['facebookDomainVerification'] = (string) $value,
                'seo.pinterest_site_verification' => $data['pinterestSiteVerification'] = (string) $value,
                'seo.yandex_verification' => $data['yandexVerification'] = (string) $value,
                'seo.organization_name' => $data['organizationName'] = (string) $value,
                'seo.organization_legal_name' => $data['organizationLegalName'] = (string) $value,
                'seo.organization_type' => $data['organizationType'] = (string) $value,
                'seo.organization_logo_url' => $data['organizationLogoUrl'] = (string) $value,
                'seo.organization_email' => $data['organizationEmail'] = (string) $value,
                'seo.organization_phone' => $data['organizationPhone'] = (string) $value,
                'seo.organization_address' => $data['organizationAddress'] = (string) $value,
                'seo.founding_date' => $data['foundingDate'] = (string) $value,
                'seo.default_author' => $data['defaultAuthor'] = (string) $value,
                'seo.enable_organization_schema' => $data['enableOrganizationSchema'] = (bool) $value,
                'seo.enable_website_schema' => $data['enableWebsiteSchema'] = (bool) $value,
                'seo.enable_news_article_schema' => $data['enableNewsArticleSchema'] = (bool) $value,
                'seo.enable_rss_links' => $data['enableRssLinks'] = (bool) $value,
                'seo.robots_extra' => $data['robotsExtra'] = (string) $value,
                'seo.extra_head_html' => $data['extraHeadHtml'] = (string) $value,
                default => null,
            };
        }

        if ($data['titleSeparator'] === '') {
            $data['titleSeparator'] = ' — ';
        }
        if ($data['ogLocale'] === '') {
            $data['ogLocale'] = 'es_ES';
        }
        if ($data['defaultRobots'] === '' || !array_key_exists($data['defaultRobots'], SiteSeoConfig::ROBOTS_PRESETS)) {
            $data['defaultRobots'] = 'index, follow';
        }

        return new SiteSeoConfig(
            homeTitle: $data['homeTitle'],
            metaDescription: $data['metaDescription'],
            metaKeywords: $data['metaKeywords'],
            titleSeparator: $data['titleSeparator'],
            defaultRobots: $data['defaultRobots'],
            defaultOgImage: $data['defaultOgImage'],
            themeColor: $data['themeColor'],
            ogSiteName: $data['ogSiteName'],
            ogLocale: $data['ogLocale'],
            twitterSite: $data['twitterSite'],
            facebookAppId: $data['facebookAppId'],
            facebookAdmins: $data['facebookAdmins'],
            googleSiteVerification: $data['googleSiteVerification'],
            bingSiteVerification: $data['bingSiteVerification'],
            facebookDomainVerification: $data['facebookDomainVerification'],
            pinterestSiteVerification: $data['pinterestSiteVerification'],
            yandexVerification: $data['yandexVerification'],
            organizationName: $data['organizationName'],
            organizationLegalName: $data['organizationLegalName'],
            organizationType: $data['organizationType'],
            organizationLogoUrl: $data['organizationLogoUrl'],
            organizationEmail: $data['organizationEmail'],
            organizationPhone: $data['organizationPhone'],
            organizationAddress: $data['organizationAddress'],
            foundingDate: $data['foundingDate'],
            defaultAuthor: $data['defaultAuthor'],
            enableOrganizationSchema: $data['enableOrganizationSchema'],
            enableWebsiteSchema: $data['enableWebsiteSchema'],
            enableNewsArticleSchema: $data['enableNewsArticleSchema'],
            enableRssLinks: $data['enableRssLinks'],
            robotsExtra: $data['robotsExtra'],
            extraHeadHtml: $data['extraHeadHtml'],
        );
    }

    public function getTvConfig(SiteContext $context): TvConfig
    {
        $data = [
            'enabled' => false,
            'channelIds' => \App\Tv\TvChannelRegistry::DEFAULT_CHANNEL_IDS,
            'feedUrl' => TvConfig::DEFAULT_FEED_URL,
            'timezone' => 'Europe/Madrid',
            'cachedAt' => null,
            'cachedJson' => '',
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'tv.enabled' => $data['enabled'] = (bool) $value,
                'tv.channel_ids' => $data['channelIds'] = $this->decodeTvChannelIds($value),
                'tv.feed_url' => $data['feedUrl'] = (string) $value,
                'tv.timezone' => $data['timezone'] = (string) $value,
                'tv.cached_at' => $data['cachedAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                'tv.cached_json' => $data['cachedJson'] = (string) $value,
                default => null,
            };
        }

        return new TvConfig(
            enabled: $data['enabled'],
            channelIds: \App\Tv\TvChannelRegistry::normalizeIds($data['channelIds']),
            feedUrl: $data['feedUrl'] !== '' ? $data['feedUrl'] : TvConfig::DEFAULT_FEED_URL,
            timezone: $data['timezone'] !== '' ? $data['timezone'] : 'Europe/Madrid',
            cachedAt: $data['cachedAt'],
            cachedJson: $data['cachedJson'],
        );
    }

    public function getGamesConfig(SiteContext $context): GamesConfig
    {
        $data = [
            'enabled' => false,
            'sudokuEnabled' => true,
            'wordScrambleEnabled' => true,
            'sudokuCachedJson' => '',
            'sudokuCachedDate' => '',
            'wordScrambleCachedJson' => '',
            'wordScrambleCachedDate' => '',
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'games.enabled' => $data['enabled'] = (bool) $value,
                'games.sudoku_enabled' => $data['sudokuEnabled'] = (bool) $value,
                'games.word_scramble_enabled' => $data['wordScrambleEnabled'] = (bool) $value,
                'games.sudoku_cached_json' => $data['sudokuCachedJson'] = (string) $value,
                'games.sudoku_cached_date' => $data['sudokuCachedDate'] = (string) $value,
                'games.word_scramble_cached_json' => $data['wordScrambleCachedJson'] = (string) $value,
                'games.word_scramble_cached_date' => $data['wordScrambleCachedDate'] = (string) $value,
                default => null,
            };
        }

        return new GamesConfig(
            enabled: $data['enabled'],
            sudokuEnabled: $data['sudokuEnabled'],
            wordScrambleEnabled: $data['wordScrambleEnabled'],
            sudokuCachedJson: $data['sudokuCachedJson'],
            sudokuCachedDate: $data['sudokuCachedDate'],
            wordScrambleCachedJson: $data['wordScrambleCachedJson'],
            wordScrambleCachedDate: $data['wordScrambleCachedDate'],
        );
    }

    public function getAiConfig(SiteContext $context): AiConfig
    {
        $data = [
            'openaiApiKey' => '',
            'model' => 'gpt-4o-mini',
            'articleImprovePrompt' => AiConfig::DEFAULT_ARTICLE_IMPROVE_PROMPT,
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'ai.openai_api_key' => $data['openaiApiKey'] = (string) $value,
                'ai.model' => $data['model'] = (string) $value,
                'ai.article_improve_prompt' => $data['articleImprovePrompt'] = (string) $value,
                default => null,
            };
        }

        // Compatibilidad: si aún no hay clave central, reutiliza la de un módulo.
        if ($data['openaiApiKey'] === '') {
            foreach ([$this->getWeatherConfig($context), $this->getMarketConfig($context), $this->getHoroscopeConfig($context)] as $module) {
                if ($module->openaiApiKey !== '') {
                    $data['openaiApiKey'] = $module->openaiApiKey;
                    break;
                }
            }
        }

        $model = trim($data['model']);
        if ($model === '') {
            $model = 'gpt-4o-mini';
        }

        $envKey = trim($this->defaultOpenaiApiKey);
        $prompt = trim($data['articleImprovePrompt']);
        if ($prompt === '') {
            $prompt = AiConfig::DEFAULT_ARTICLE_IMPROVE_PROMPT;
        }

        return new AiConfig(
            openaiApiKey: $data['openaiApiKey'],
            hasOpenaiApiKey: $data['openaiApiKey'] !== '',
            model: $model,
            envKeyAvailable: $envKey !== '',
            articleImprovePrompt: $prompt,
        );
    }

    public function getHoroscopeConfig(SiteContext $context): HoroscopeConfig
    {
        $data = [
            'enabled' => false,
            'aiEnabled' => false,
            'openaiApiKey' => '',
            'dailyJson' => '',
            'dailyCachedAt' => null,
            'dailyDateKey' => '',
        ];

        foreach ($this->loadSettings($context) as $setting) {
            $value = $setting->getValue()['value'] ?? null;
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'horoscope.enabled' => $data['enabled'] = (bool) $value,
                'horoscope.ai_enabled' => $data['aiEnabled'] = (bool) $value,
                'horoscope.openai_api_key' => $data['openaiApiKey'] = (string) $value,
                'horoscope.daily_json' => $data['dailyJson'] = (string) $value,
                'horoscope.daily_cached_at' => $data['dailyCachedAt'] = is_string($value) && $value !== ''
                    ? new \DateTimeImmutable($value) : null,
                'horoscope.daily_date_key' => $data['dailyDateKey'] = (string) $value,
                default => null,
            };
        }

        return new HoroscopeConfig(
            enabled: $data['enabled'],
            aiEnabled: $data['aiEnabled'],
            openaiApiKey: $data['openaiApiKey'],
            hasOpenaiApiKey: $data['openaiApiKey'] !== '',
            dailyJson: $data['dailyJson'],
            dailyCachedAt: $data['dailyCachedAt'],
            dailyDateKey: $data['dailyDateKey'],
        );
    }

    public function getClassifiedsConfig(SiteContext $context): ClassifiedsConfig
    {
        $enabled = true;

        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace().'.'.$setting->getKeyName() === 'classifieds.enabled') {
                $enabled = (bool) ($setting->getValue()['value'] ?? false);
            }
        }

        return new ClassifiedsConfig(enabled: $enabled);
    }

    public function getForumConfig(SiteContext $context): ForumConfig
    {
        $enabled = true;

        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace().'.'.$setting->getKeyName() === 'forum.enabled') {
                $enabled = (bool) ($setting->getValue()['value'] ?? false);
            }
        }

        return new ForumConfig(enabled: $enabled);
    }

    public function getError404Config(SiteContext $context): \App\Core\Site\Dto\Error404Config
    {
        $defaults = new \App\Core\Site\Dto\Error404Config();
        $title = $defaults->title;
        $message = $defaults->message;
        $showSearch = $defaults->showSearch;
        $showHomeButton = $defaults->showHomeButton;
        $showLatestArticles = $defaults->showLatestArticles;
        $searchPlaceholder = $defaults->searchPlaceholder;
        $homeButtonLabel = $defaults->homeButtonLabel;
        $extraHtml = $defaults->extraHtml;

        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace() !== 'error404') {
                continue;
            }
            $value = $setting->getValue()['value'] ?? null;
            switch ($setting->getKeyName()) {
                case 'title':
                    if (is_string($value) && trim($value) !== '') {
                        $title = trim($value);
                    }
                    break;
                case 'message':
                    if (is_string($value) && trim($value) !== '') {
                        $message = trim($value);
                    }
                    break;
                case 'show_search':
                    $showSearch = (bool) $value;
                    break;
                case 'show_home_button':
                    $showHomeButton = (bool) $value;
                    break;
                case 'show_latest_articles':
                    $showLatestArticles = (bool) $value;
                    break;
                case 'search_placeholder':
                    if (is_string($value) && trim($value) !== '') {
                        $searchPlaceholder = trim($value);
                    }
                    break;
                case 'home_button_label':
                    if (is_string($value) && trim($value) !== '') {
                        $homeButtonLabel = trim($value);
                    }
                    break;
                case 'extra_html':
                    if (is_string($value)) {
                        $extraHtml = $value;
                    }
                    break;
            }
        }

        return new \App\Core\Site\Dto\Error404Config(
            title: $title,
            message: $message,
            showSearch: $showSearch,
            showHomeButton: $showHomeButton,
            showLatestArticles: $showLatestArticles,
            searchPlaceholder: $searchPlaceholder,
            homeButtonLabel: $homeButtonLabel,
            extraHtml: $extraHtml,
        );
    }

    /** @return list<string> */
    private function decodeTvChannelIds(mixed $value): array
    {
        if (is_array($value)) {
            return \App\Tv\TvChannelRegistry::normalizeIds(array_values(array_filter(
                $value,
                static fn (mixed $id): bool => is_string($id),
            )));
        }

        if (!is_string($value) || $value === '') {
            return \App\Tv\TvChannelRegistry::DEFAULT_CHANNEL_IDS;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return \App\Tv\TvChannelRegistry::DEFAULT_CHANNEL_IDS;
        }

        if (!is_array($decoded)) {
            return \App\Tv\TvChannelRegistry::DEFAULT_CHANNEL_IDS;
        }

        return \App\Tv\TvChannelRegistry::normalizeIds(array_values(array_filter(
            $decoded,
            static fn (mixed $id): bool => is_string($id),
        )));
    }

    /** @return list<string> */
    private function decodeMarketIndexIds(mixed $value): array
    {
        if (is_array($value)) {
            return \App\Markets\MarketIndexRegistry::normalizeIds(array_values(array_filter(
                $value,
                static fn (mixed $id): bool => is_string($id),
            )));
        }

        if (!is_string($value) || $value === '') {
            return \App\Markets\MarketIndexRegistry::DEFAULT_INDEX_IDS;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return \App\Markets\MarketIndexRegistry::DEFAULT_INDEX_IDS;
        }

        if (!is_array($decoded)) {
            return \App\Markets\MarketIndexRegistry::DEFAULT_INDEX_IDS;
        }

        return \App\Markets\MarketIndexRegistry::normalizeIds(array_values(array_filter(
            $decoded,
            static fn (mixed $id): bool => is_string($id),
        )));
    }

    public function getNavMenuConfig(SiteContext $context): \App\Core\Site\Dto\NavMenuConfig
    {
        $mainJson = '';
        $footerSectionsJson = '';
        $footerLegalJson = '';

        foreach ($this->loadSettings($context) as $setting) {
            $raw = $setting->getValue()['value'] ?? null;
            if (!is_string($raw)) {
                continue;
            }
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'navigation.main_json' => $mainJson = $raw,
                'navigation.footer_sections_json' => $footerSectionsJson = $raw,
                'navigation.footer_legal_json' => $footerLegalJson = $raw,
                default => null,
            };
        }

        $normalizer = new \App\Navigation\NavMenuNormalizer();

        return $normalizer->toConfig(
            $this->decodeNavMenuJson($mainJson),
            $this->decodeNavMenuJson($footerSectionsJson),
            $this->decodeNavMenuJson($footerLegalJson),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeNavMenuJson(string $json): array
    {
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function getLayoutConfig(SiteContext $context): SiteLayoutConfig
    {
        $home = HomeLayoutTemplate::Classic->value;
        $category = CategoryLayoutTemplate::Magazine->value;
        $article = ArticleLayoutTemplate::Standard->value;
        $relatedArticlesCount = 8;
        $homeCategoryArticlesCount = 4;
        $sidebarPopularCount = 5;
        $sidebarLatestCount = 5;

        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace() !== 'layout') {
                continue;
            }
            $raw = $setting->getValue()['value'] ?? '';
            $value = is_scalar($raw) ? (string) $raw : '';
            match ($setting->getNamespace().'.'.$setting->getKeyName()) {
                'layout.home_template' => $home = $value,
                'layout.category_template' => $category = $value,
                'layout.article_template' => $article = $value,
                'layout.related_articles_count' => $relatedArticlesCount = max(2, min(8, (int) $value)),
                'layout.home_category_articles_count' => $homeCategoryArticlesCount = max(2, min(4, (int) $value)),
                'layout.sidebar_popular_count' => $sidebarPopularCount = SiteLayoutConfig::clampSidebarCount((int) $value),
                'layout.sidebar_latest_count' => $sidebarLatestCount = SiteLayoutConfig::clampSidebarCount((int) $value),
                default => null,
            };
        }

        return new SiteLayoutConfig(
            homeTemplate: HomeLayoutTemplate::tryFrom($home) ?? HomeLayoutTemplate::Classic,
            categoryTemplate: CategoryLayoutTemplate::tryFrom($category) ?? CategoryLayoutTemplate::Magazine,
            articleTemplate: ArticleLayoutTemplate::tryFrom($article) ?? ArticleLayoutTemplate::Standard,
            relatedArticlesCount: max(2, min(8, $relatedArticlesCount)),
            homeCategoryArticlesCount: max(2, min(4, $homeCategoryArticlesCount)),
            sidebarPopularCount: SiteLayoutConfig::clampSidebarCount($sidebarPopularCount),
            sidebarLatestCount: SiteLayoutConfig::clampSidebarCount($sidebarLatestCount),
        );
    }

    public function getContentModulesConfig(SiteContext $context): ContentModulesConfig
    {
        $raw = [];
        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace() !== 'content_modules' || $setting->getKeyName() !== 'surfaces') {
                continue;
            }
            $value = $setting->getValue()['value'] ?? null;
            if (\is_string($value) && $value !== '') {
                try {
                    $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    $raw = \is_array($decoded) ? $decoded : [];
                } catch (\Throwable) {
                    $raw = [];
                }
            } elseif (\is_array($value)) {
                $raw = $value;
            }
        }

        return ContentModulesConfig::fromStored($raw);
    }

    public function getPermissionsConfig(SiteContext $context): \App\Core\Site\Dto\SitePermissionsConfig
    {
        foreach ($this->loadSettings($context) as $setting) {
            if ($setting->getNamespace() === 'permissions' && $setting->getKeyName() === 'matrix_json') {
                return \App\Core\Site\Dto\SitePermissionsConfig::fromJson($setting->getValue()['value'] ?? null);
            }
        }

        return \App\Core\Site\Dto\SitePermissionsConfig::fromDefaults();
    }

    /** @return list<SiteSetting> */
    private function loadSettings(SiteContext $context): array
    {
        try {
            $em = $this->siteEmFactory->forContext($context);

            return $em->getRepository(SiteSetting::class)->findAll();
        } catch (\Throwable) {
            return [];
        }
    }
}
