<?php

namespace App\LocalServices;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Weather\WeatherService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class LocalServicesLocator
{
    public const SESSION_KEY = 'local_services_postal_code';

    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly SiteSettingsReader $settingsReader,
        private readonly RequestStack $requestStack,
    ) {}

    public function resolve(SiteContext $site, ?string $requestedPostal = null): ?LocalServicesLocation
    {
        $postal = $this->normalizePostal($requestedPostal ?? $this->currentPostalPreference($site));
        if ($postal === null) {
            return null;
        }

        $weather = $this->settingsReader->getWeatherConfig($site);
        $fromDefault = $postal === $this->normalizePostal($weather->postalCode);

        if (
            $fromDefault
            && $weather->postalCode !== ''
            && $weather->latitude !== null
            && $weather->longitude !== null
            && $weather->placeName !== ''
        ) {
            return new LocalServicesLocation(
                postalCode: $weather->postalCode,
                placeName: $weather->placeName,
                latitude: $weather->latitude,
                longitude: $weather->longitude,
                provinceCode: substr($weather->postalCode, 0, 2),
                fromSiteDefault: true,
            );
        }

        try {
            $geo = $this->weatherService->geocodePostalCode($postal);
        } catch (\Throwable) {
            return null;
        }

        return new LocalServicesLocation(
            postalCode: $postal,
            placeName: $geo['placeName'],
            latitude: $geo['latitude'],
            longitude: $geo['longitude'],
            provinceCode: substr($postal, 0, 2),
            fromSiteDefault: $fromDefault,
        );
    }

    public function rememberPostal(string $postalCode): void
    {
        $postal = $this->normalizePostal($postalCode);
        $session = $this->requestStack->getSession();
        if ($postal === null) {
            $session->remove(self::SESSION_KEY);

            return;
        }

        $session->set(self::SESSION_KEY, $postal);
    }

    public function defaultPostalHint(SiteContext $site): string
    {
        return $this->normalizePostal($this->settingsReader->getWeatherConfig($site)->postalCode) ?? '';
    }

    private function currentPostalPreference(SiteContext $site): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request instanceof Request) {
            $query = $this->normalizePostal((string) $request->query->get('cp', ''));
            if ($query !== null) {
                return $query;
            }
        }

        $session = $this->requestStack->getSession();
        $stored = $this->normalizePostal((string) $session->get(self::SESSION_KEY, ''));
        if ($stored !== null) {
            return $stored;
        }

        return $this->defaultPostalHint($site);
    }

    private function normalizePostal(?string $value): ?string
    {
        $postal = preg_replace('/\D/', '', (string) $value) ?? '';
        if (strlen($postal) !== 5) {
            return null;
        }

        return $postal;
    }
}
