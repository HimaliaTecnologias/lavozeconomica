<?php

namespace App\Core\Site\Dto;

final readonly class SiteFooterConfig
{
    public function __construct(
        public string $contactEmail = '',
        public string $contactPhone = '',
        public string $contactAddress = '',
        public string $facebookUrl = '',
        public string $twitterUrl = '',
        public string $instagramUrl = '',
        public string $youtubeUrl = '',
        public string $linkedinUrl = '',
        public string $whatsappUrl = '',
        public string $tiktokUrl = '',
        public string $telegramUrl = '',
    ) {}

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'contactEmail' => $this->contactEmail,
            'contactPhone' => $this->contactPhone,
            'contactAddress' => $this->contactAddress,
            'facebookUrl' => $this->facebookUrl,
            'twitterUrl' => $this->twitterUrl,
            'instagramUrl' => $this->instagramUrl,
            'youtubeUrl' => $this->youtubeUrl,
            'linkedinUrl' => $this->linkedinUrl,
            'whatsappUrl' => $this->whatsappUrl,
            'tiktokUrl' => $this->tiktokUrl,
            'telegramUrl' => $this->telegramUrl,
        ];
    }

    public function hasSocialLinks(): bool
    {
        return $this->socialLinks() !== [];
    }

    /**
     * Handle de X/Twitter con @, a partir de la URL configurada en el pie.
     * Ejemplo: https://x.com/territorio → @territorio
     */
    public function twitterHandle(): ?string
    {
        $url = trim($this->twitterUrl);
        if ($url === '') {
            return null;
        }

        if (preg_match('~(?:twitter\.com|x\.com)/@?([A-Za-z0-9_]{1,15})(?:[/?#]|$)~i', $url, $matches) === 1) {
            return '@'.$matches[1];
        }

        if (preg_match('~^@?([A-Za-z0-9_]{1,15})$~', $url, $matches) === 1) {
            return '@'.$matches[1];
        }

        return null;
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public function socialLinks(): array
    {
        $links = [];
        foreach ($this->socialUrlMap() as $key => $url) {
            if ($url === '') {
                continue;
            }
            $links[] = [
                'key' => $key,
                'label' => $this->socialLabel($key),
                'url' => $url,
            ];
        }

        return $links;
    }

    /** @return array<string, string> */
    private function socialUrlMap(): array
    {
        return [
            'facebook' => $this->facebookUrl,
            'twitter' => $this->twitterUrl,
            'instagram' => $this->instagramUrl,
            'youtube' => $this->youtubeUrl,
            'linkedin' => $this->linkedinUrl,
            'whatsapp' => $this->whatsappUrl,
            'tiktok' => $this->tiktokUrl,
            'telegram' => $this->telegramUrl,
        ];
    }

    private function socialLabel(string $key): string
    {
        return match ($key) {
            'facebook' => 'Facebook',
            'twitter' => 'X',
            'instagram' => 'Instagram',
            'youtube' => 'YouTube',
            'linkedin' => 'LinkedIn',
            'whatsapp' => 'WhatsApp',
            'tiktok' => 'TikTok',
            'telegram' => 'Telegram',
            default => $key,
        };
    }
}
