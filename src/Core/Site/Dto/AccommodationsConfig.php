<?php

namespace App\Core\Site\Dto;

final readonly class AccommodationsConfig
{
    public function __construct(
        public bool $enabled = false,
        public string $title = 'Alojamientos',
        public string $widgetHtml = '',
        public string $postalCode = '',
        public string $placeName = '',
    ) {}

    public function isVisible(): bool
    {
        return $this->enabled
            && trim($this->widgetHtml) !== ''
            && $this->postalCode !== '';
    }

    public function renderedHtml(): string
    {
        $html = $this->widgetHtml;
        $replacements = [
            '{postal_code}' => $this->postalCode,
            '{{postal_code}}' => $this->postalCode,
            '{cp}' => $this->postalCode,
            '{place_name}' => $this->placeName,
            '{{place_name}}' => $this->placeName,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $html);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'title' => $this->title,
            'widgetHtml' => $this->widgetHtml,
            'postalCode' => $this->postalCode,
            'placeName' => $this->placeName,
        ];
    }
}
