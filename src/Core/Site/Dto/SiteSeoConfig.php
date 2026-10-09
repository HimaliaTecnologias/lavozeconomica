<?php

namespace App\Core\Site\Dto;

final readonly class SiteSeoConfig
{
    public const ORGANIZATION_TYPES = [
        'NewsMediaOrganization' => 'Medio de comunicación (recomendado)',
        'Organization' => 'Organización genérica',
        'Newspaper' => 'Periódico',
    ];

    public const ROBOTS_PRESETS = [
        'index, follow' => 'Indexar y seguir enlaces (recomendado)',
        'index, nofollow' => 'Indexar sin seguir enlaces',
        'noindex, follow' => 'No indexar, sí seguir enlaces',
        'noindex, nofollow' => 'No indexar ni seguir enlaces',
    ];

    public function __construct(
        public string $homeTitle = '',
        public string $metaDescription = '',
        public string $metaKeywords = '',
        public string $titleSeparator = ' — ',
        public string $defaultRobots = 'index, follow',
        public string $defaultOgImage = '',
        public string $themeColor = '',
        public string $ogSiteName = '',
        public string $ogLocale = 'es_ES',
        public string $twitterSite = '',
        public string $facebookAppId = '',
        public string $facebookAdmins = '',
        public string $googleSiteVerification = '',
        public string $bingSiteVerification = '',
        public string $facebookDomainVerification = '',
        public string $pinterestSiteVerification = '',
        public string $yandexVerification = '',
        public string $organizationName = '',
        public string $organizationLegalName = '',
        public string $organizationType = 'NewsMediaOrganization',
        public string $organizationLogoUrl = '',
        public string $organizationEmail = '',
        public string $organizationPhone = '',
        public string $organizationAddress = '',
        public string $foundingDate = '',
        public string $defaultAuthor = '',
        public bool $enableOrganizationSchema = true,
        public bool $enableWebsiteSchema = true,
        public bool $enableNewsArticleSchema = true,
        public bool $enableRssLinks = true,
        public string $robotsExtra = '',
        public string $extraHeadHtml = '',
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'homeTitle' => $this->homeTitle,
            'metaDescription' => $this->metaDescription,
            'metaKeywords' => $this->metaKeywords,
            'titleSeparator' => $this->titleSeparator,
            'defaultRobots' => $this->defaultRobots,
            'defaultOgImage' => $this->defaultOgImage,
            'themeColor' => $this->themeColor,
            'ogSiteName' => $this->ogSiteName,
            'ogLocale' => $this->ogLocale,
            'twitterSite' => $this->twitterSite,
            'facebookAppId' => $this->facebookAppId,
            'facebookAdmins' => $this->facebookAdmins,
            'googleSiteVerification' => $this->googleSiteVerification,
            'bingSiteVerification' => $this->bingSiteVerification,
            'facebookDomainVerification' => $this->facebookDomainVerification,
            'pinterestSiteVerification' => $this->pinterestSiteVerification,
            'yandexVerification' => $this->yandexVerification,
            'organizationName' => $this->organizationName,
            'organizationLegalName' => $this->organizationLegalName,
            'organizationType' => $this->organizationType,
            'organizationLogoUrl' => $this->organizationLogoUrl,
            'organizationEmail' => $this->organizationEmail,
            'organizationPhone' => $this->organizationPhone,
            'organizationAddress' => $this->organizationAddress,
            'foundingDate' => $this->foundingDate,
            'defaultAuthor' => $this->defaultAuthor,
            'enableOrganizationSchema' => $this->enableOrganizationSchema,
            'enableWebsiteSchema' => $this->enableWebsiteSchema,
            'enableNewsArticleSchema' => $this->enableNewsArticleSchema,
            'enableRssLinks' => $this->enableRssLinks,
            'robotsExtra' => $this->robotsExtra,
            'extraHeadHtml' => $this->extraHeadHtml,
        ];
    }

    public function resolvedSiteName(string $siteName): string
    {
        return $this->ogSiteName !== '' ? $this->ogSiteName : $siteName;
    }

    public function resolvedOrganizationName(string $siteName): string
    {
        return $this->organizationName !== '' ? $this->organizationName : $this->resolvedSiteName($siteName);
    }

    public function resolvedHomeTitle(string $siteName): string
    {
        return $this->homeTitle !== '' ? $this->homeTitle : $siteName;
    }

    public function resolvedTwitterHandle(?string $footerHandle = null): ?string
    {
        $handle = trim($this->twitterSite);
        if ($handle !== '') {
            if (!str_starts_with($handle, '@')) {
                $handle = '@'.$handle;
            }

            return $handle;
        }

        return $footerHandle !== null && $footerHandle !== '' ? $footerHandle : null;
    }

    public function resolvedDefaultImage(string $fallbackLogo = ''): string
    {
        if ($this->defaultOgImage !== '') {
            return $this->defaultOgImage;
        }

        return $fallbackLogo;
    }

    public function resolvedOrganizationLogo(string $fallbackLogo = ''): string
    {
        if ($this->organizationLogoUrl !== '') {
            return $this->organizationLogoUrl;
        }

        return $fallbackLogo;
    }

    public function resolvedRobots(?string $pageRobots = null): string
    {
        if ($pageRobots !== null && $pageRobots !== '') {
            return $this->enrichIndexableRobots($pageRobots);
        }

        $robots = strtolower(trim($this->defaultRobots));
        if ($robots === '' || str_contains($robots, 'noindex')) {
            return $this->enrichIndexableRobots('index, follow');
        }

        return $this->enrichIndexableRobots(
            $this->defaultRobots !== '' ? $this->defaultRobots : 'index, follow',
        );
    }

    /**
     * Directivas recomendadas por Google para medios (Discover / rich results).
     * No se añaden si la página es noindex.
     */
    public function enrichIndexableRobots(string $robots): string
    {
        $robots = trim($robots);
        if ($robots === '') {
            $robots = 'index, follow';
        }

        $lower = strtolower($robots);
        if (str_contains($lower, 'noindex')) {
            return $robots;
        }

        $extras = [];
        if (!str_contains($lower, 'max-image-preview')) {
            $extras[] = 'max-image-preview:large';
        }
        if (!str_contains($lower, 'max-snippet')) {
            $extras[] = 'max-snippet:-1';
        }
        if (!str_contains($lower, 'max-video-preview')) {
            $extras[] = 'max-video-preview:-1';
        }

        if ($extras === []) {
            return $robots;
        }

        return rtrim($robots, ', ').', '.implode(', ', $extras);
    }

    public function organizationTypeSafe(): string
    {
        return array_key_exists($this->organizationType, self::ORGANIZATION_TYPES)
            ? $this->organizationType
            : 'NewsMediaOrganization';
    }

    /**
     * Líneas extra para robots.txt (sin User-agent/Sitemap).
     *
     * @return list<string>
     */
    public function robotsExtraLines(): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $this->robotsExtra) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^(user-agent|sitemap)\s*:/i', $line) === 1) {
                continue;
            }
            if (preg_match('/^disallow:\s*\/\s*$/i', $line) === 1) {
                continue;
            }
            if (preg_match('/noindex/i', $line) === 1) {
                continue;
            }
            $lines[] = $line;
        }

        return $lines;
    }
}
