<?php

namespace App\Core\Site\Dto;

use App\Editorial\SiteFontRegistry;

final readonly class SiteAppearanceConfig
{
    public function __construct(
        public string $tagline = '',
        public string $metaDescription = '',
        public string $metaKeywords = '',
        public string $primaryColor = '#b91c1c',
        public string $secondaryColor = '#7f1d1d',
        public string $backgroundColor = '#ffffff',
        public string $surfaceColor = '#f4f4f4',
        public string $textColor = '#111111',
        public string $textMutedColor = '#666666',
        public string $headingColor = '#111111',
        public string $linkColor = '',
        public string $borderColor = '#d8d8d8',
        public string $topBarColor = '#1a1a1a',
        public string $navColor = '',
        public string $navBarColor = '#ffffff',
        public string $footerColor = '#12151c',
        public string $headingFont = SiteFontRegistry::DEFAULT_HEADING_FONT,
        public string $bodyFont = SiteFontRegistry::DEFAULT_BODY_FONT,
        public string $articleFont = SiteFontRegistry::DEFAULT_ARTICLE_FONT,
        public string $logoUrl = '',
        public string $logoDarkUrl = '',
        public string $logoMarkUrl = '',
        public string $faviconUrl = '',
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'tagline' => $this->tagline,
            'metaDescription' => $this->metaDescription,
            'metaKeywords' => $this->metaKeywords,
            'primaryColor' => $this->primaryColor,
            'secondaryColor' => $this->secondaryColor,
            'backgroundColor' => $this->backgroundColor,
            'surfaceColor' => $this->surfaceColor,
            'textColor' => $this->textColor,
            'textMutedColor' => $this->textMutedColor,
            'headingColor' => $this->headingColor,
            'linkColor' => $this->linkColor,
            'borderColor' => $this->borderColor,
            'topBarColor' => $this->topBarColor,
            'navColor' => $this->navColor,
            'navBarColor' => $this->navBarColor,
            'footerColor' => $this->footerColor,
            'headingFont' => $this->headingFont,
            'bodyFont' => $this->bodyFont,
            'articleFont' => $this->articleFont,
            'logoUrl' => $this->logoUrl,
            'logoDarkUrl' => $this->logoDarkUrl,
            'logoMarkUrl' => $this->logoMarkUrl,
            'faviconUrl' => $this->faviconUrl,
        ];
    }

    public function linkColorResolved(): string
    {
        return $this->linkColor !== '' ? $this->linkColor : $this->primaryColor;
    }

    public function navColorResolved(): string
    {
        return $this->navColor !== '' ? $this->navColor : $this->primaryColor;
    }

    public function navBarColorResolved(): string
    {
        return $this->navBarColor !== '' ? $this->navBarColor : $this->backgroundColor;
    }

    public function topBarFg(): string
    {
        return self::contrastForeground($this->topBarColor);
    }

    public function footerFg(): string
    {
        return self::contrastForeground($this->footerColor);
    }

    public function navBarFg(): string
    {
        return self::contrastForeground($this->navBarColorResolved());
    }

    public function topBarTone(): string
    {
        return self::isDark($this->topBarColor) ? 'dark' : 'light';
    }

    public function footerTone(): string
    {
        return self::isDark($this->footerColor) ? 'dark' : 'light';
    }

    public function navBarTone(): string
    {
        return self::isDark($this->navBarColorResolved()) ? 'dark' : 'light';
    }

    public static function contrastForeground(string $backgroundHex): string
    {
        return self::isDark($backgroundHex) ? '#ffffff' : '#111111';
    }

    public static function isDark(string $hex): bool
    {
        return self::relativeLuminance($hex) < 0.45;
    }

    public static function relativeLuminance(string $hex): float
    {
        $hex = ltrim(trim($hex), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return 0.0;
        }

        $channel = static function (string $part): float {
            $value = hexdec($part) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel(substr($hex, 0, 2))
            + 0.7152 * $channel(substr($hex, 2, 2))
            + 0.0722 * $channel(substr($hex, 4, 2));
    }
}
