<?php

namespace App\Weather;

use App\Core\Site\Dto\WeatherConfig;
use App\Core\Site\Dto\WeatherForecastView;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeatherService
{
    private const CACHE_TTL_SECONDS = 1800;

    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function getConfig(SiteContext $context): WeatherConfig
    {
        return $this->settingsReader->getWeatherConfig($context);
    }

    public function getForecast(SiteContext $context, bool $forceRefresh = false): ?WeatherForecastView
    {
        $config = $this->getConfig($context);
        if (!$config->enabled || !$config->isConfigured()) {
            return null;
        }

        if (!$forceRefresh && $this->isCacheFresh($config)) {
            $cached = $this->loadCachedForecast($config);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $forecast = $this->fetchForecast($config);
            $this->persistCache($context, $config, $forecast);

            return $forecast;
        } catch (\Throwable) {
            return $this->loadCachedForecast($config);
        }
    }

    /**
     * @return array{placeName: string, latitude: float, longitude: float}
     */
    public function geocodePostalCode(string $postalCode): array
    {
        $postalCode = preg_replace('/\D/', '', $postalCode) ?? '';
        if (strlen($postalCode) !== 5) {
            throw new \InvalidArgumentException('El código postal debe tener 5 dígitos.');
        }

        $response = $this->httpClient->request('GET', 'https://api.zippopotam.us/es/'.$postalCode, [
            'timeout' => 15,
        ]);
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $place = $data['places'][0] ?? null;
        if (!is_array($place)) {
            throw new \InvalidArgumentException('No se encontró la localidad para ese código postal.');
        }

        $placeName = trim((string) ($place['place name'] ?? ''));
        $latitude = (float) ($place['latitude'] ?? 0);
        $longitude = (float) ($place['longitude'] ?? 0);
        if ($placeName === '' || $latitude === 0.0 && $longitude === 0.0) {
            throw new \InvalidArgumentException('Datos de geolocalización incompletos para el código postal.');
        }

        return [
            'placeName' => $placeName,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private function fetchForecast(WeatherConfig $config): WeatherForecastView
    {
        $latitude = $config->latitude;
        $longitude = $config->longitude;
        if ($latitude === null || $longitude === null) {
            throw new \RuntimeException('Coordenadas no configuradas.');
        }

        $url = sprintf(
            'https://api.open-meteo.com/v1/forecast?latitude=%s&longitude=%s&current=temperature_2m,weather_code&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max&timezone=auto&forecast_days=7',
            rawurlencode((string) $latitude),
            rawurlencode((string) $longitude),
        );

        $response = $this->httpClient->request('GET', $url, ['timeout' => 20]);
        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $current = $data['current'] ?? [];
        $daily = $data['daily'] ?? [];
        $times = $daily['time'] ?? [];
        $codes = $daily['weather_code'] ?? [];
        $maxTemps = $daily['temperature_2m_max'] ?? [];
        $minTemps = $daily['temperature_2m_min'] ?? [];
        $precip = $daily['precipitation_probability_max'] ?? [];

        $days = [];
        foreach ($times as $i => $date) {
            $code = (int) ($codes[$i] ?? 0);
            $days[] = [
                'date' => (string) $date,
                'label' => $this->formatDayLabel((string) $date),
                'min' => isset($minTemps[$i]) ? round((float) $minTemps[$i], 1) : null,
                'max' => isset($maxTemps[$i]) ? round((float) $maxTemps[$i], 1) : null,
                'code' => $code,
                'description' => WeatherCodeMapper::describe($code),
                'precip' => isset($precip[$i]) ? (int) $precip[$i] : null,
            ];
        }

        $currentCode = (int) ($current['weather_code'] ?? 0);

        return new WeatherForecastView(
            placeName: $config->placeName,
            postalCode: $config->postalCode,
            currentTemp: isset($current['temperature_2m']) ? round((float) $current['temperature_2m'], 1) : null,
            currentWeatherCode: $currentCode,
            currentDescription: WeatherCodeMapper::describe($currentCode),
            daily: $days,
            fetchedAt: new \DateTimeImmutable(),
        );
    }

    private function isCacheFresh(WeatherConfig $config): bool
    {
        if ($config->cachedAt === null) {
            return false;
        }

        return $config->cachedAt->getTimestamp() + self::CACHE_TTL_SECONDS > time();
    }

    private function loadCachedForecast(WeatherConfig $config): ?WeatherForecastView
    {
        $raw = $config->cachedJson;
        if ($raw === '') {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        $daily = $data['daily'] ?? [];
        if (!is_array($daily)) {
            $daily = [];
        }

        $fetchedAt = isset($data['fetchedAt']) && is_string($data['fetchedAt'])
            ? new \DateTimeImmutable($data['fetchedAt'])
            : ($config->cachedAt ?? new \DateTimeImmutable());

        return new WeatherForecastView(
            placeName: (string) ($data['placeName'] ?? $config->placeName),
            postalCode: (string) ($data['postalCode'] ?? $config->postalCode),
            currentTemp: isset($data['currentTemp']) ? (float) $data['currentTemp'] : null,
            currentWeatherCode: isset($data['currentWeatherCode']) ? (int) $data['currentWeatherCode'] : null,
            currentDescription: (string) ($data['currentDescription'] ?? ''),
            daily: $daily,
            fetchedAt: $fetchedAt,
        );
    }

    private function persistCache(SiteContext $context, WeatherConfig $config, WeatherForecastView $forecast): void
    {
        $payload = json_encode([
            'placeName' => $forecast->placeName,
            'postalCode' => $forecast->postalCode,
            'currentTemp' => $forecast->currentTemp,
            'currentWeatherCode' => $forecast->currentWeatherCode,
            'currentDescription' => $forecast->currentDescription,
            'daily' => $forecast->daily,
            'fetchedAt' => $forecast->fetchedAt->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        $this->settingsWriter->setMany($context, [
            ['weather', 'cached_json', $payload],
            ['weather', 'cached_at', $forecast->fetchedAt->format(\DateTimeInterface::ATOM)],
        ]);
    }

    private function formatDayLabel(string $date): string
    {
        try {
            $dt = new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return $date;
        }

        $today = new \DateTimeImmutable('today');
        if ($dt->format('Y-m-d') === $today->format('Y-m-d')) {
            return 'Hoy';
        }
        if ($dt->format('Y-m-d') === $today->modify('+1 day')->format('Y-m-d')) {
            return 'Mañana';
        }

        return self::DAY_NAMES[(int) $dt->format('w')].' '.$dt->format('j/n');
    }

    /** @var list<string> */
    private const DAY_NAMES = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
}
