<?php

namespace App\Core\Site\Dto;

final readonly class WeatherSponsorConfig
{
    public function __construct(
        public string $headline = 'El tiempo patrocinado por',
        public string $name = '',
        public string $logoUrl = '',
        public string $linkUrl = '',
        public string $bannerUrl = '',
        public string $html = '',
        public bool $showInNav = true,
    ) {}

    public function hasContent(): bool
    {
        return $this->name !== ''
            || $this->logoUrl !== ''
            || $this->bannerUrl !== ''
            || trim($this->html) !== '';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'headline' => $this->headline,
            'name' => $this->name,
            'logoUrl' => $this->logoUrl,
            'linkUrl' => $this->linkUrl,
            'bannerUrl' => $this->bannerUrl,
            'html' => $this->html,
            'showInNav' => $this->showInNav,
        ];
    }
}
