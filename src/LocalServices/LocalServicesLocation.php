<?php

namespace App\LocalServices;

final readonly class LocalServicesLocation
{
    public function __construct(
        public string $postalCode,
        public string $placeName,
        public float $latitude,
        public float $longitude,
        public string $provinceCode,
        public bool $fromSiteDefault = false,
    ) {}

    public function isMadridProvince(): bool
    {
        return $this->provinceCode === '28';
    }
}
