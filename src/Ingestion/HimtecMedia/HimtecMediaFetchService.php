<?php

namespace App\Ingestion\HimtecMedia;

use App\Core\Site\Dto\HimtecMediaIngestConfig;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use App\Entity\Platform\Enum\IngestedStoryStatus;
use App\Entity\Site\Article;
use App\Entity\Site\Enum\ArticleStatus;
use App\Repository\Site\ArticleRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HimtecMediaFetchService
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
        private readonly HimtecMediaIngestService $ingestService,
        private readonly HimtecMediaPayloadNormalizer $payloadNormalizer,
        private readonly IngestedStoryPublisher $publisher,
        private readonly IngestFetchHistoryRecorder $historyRecorder,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function fetch(
        SiteContext $context,
        ?HimtecMediaIngestConfig $config = null,
        string $trigger = 'manual',
    ): HimtecMediaFetchResult {
        $config ??= $this->settingsReader->getIngestConfig($context);

        if ($config->jsonUrl === '') {
            throw new \InvalidArgumentException('No hay URL JSON configurada para HimtecMedia.');
        }

        if ($config->apiKey === '') {
            throw new \InvalidArgumentException('No hay API Key configurada para HimtecMedia.');
        }

        $requestUrl = $this->buildRequestUrl($config->jsonUrl);

        try {
            $response = $this->httpClient->request('GET', $requestUrl, [
                'timeout' => 180,
                'headers' => [
                    'Accept' => 'application/json',
                    'X-API-Key' => $config->apiKey,
                ],
            ]);
            $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $result = new HimtecMediaFetchResult(
                errors: 1,
                errorMessages: [$this->translateFetchError($e, $requestUrl)],
            );
            $run = $this->persistHistory($context, $result, $trigger, $requestUrl);
            $this->recordFetchResult($context, $result);

            return $result->withRunId($run->getId());
        }

        try {
            $items = $this->normalizePayload($payload);
        } catch (\Throwable $e) {
            $result = new HimtecMediaFetchResult(
                errors: 1,
                errorMessages: [$e->getMessage()],
            );
            $run = $this->persistHistory($context, $result, $trigger, $requestUrl);
            $this->recordFetchResult($context, $result);

            return $result->withRunId($run->getId());
        }

        $received = 0;
        $duplicates = 0;
        $published = 0;
        $scheduled = 0;
        $drafts = 0;
        $errors = 0;
        $errorMessages = [];
        $itemResults = [];
        $nextSlot = $this->nextPublishSlot($context, $config);

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                ++$errors;
                $message = sprintf('Elemento #%d: no es un objeto JSON válido.', $index + 1);
                $errorMessages[] = $message;
                $itemResults[] = new HimtecMediaFetchItemResult(
                    externalId: sprintf('#%d', $index + 1),
                    title: '—',
                    outcome: 'error',
                    error: $message,
                );

                continue;
            }

            $normalized = null;
            try {
                $normalized = $this->payloadNormalizer->normalizeStory($item);
                $story = $this->ingestService->ingest($normalized);
                $isDuplicate = $story->getStatus() === IngestedStoryStatus::Duplicate;
                if ($isDuplicate) {
                    ++$duplicates;
                } else {
                    ++$received;
                }

                $scheduledAt = $this->consumePublishSlot($config, $nextSlot);
                $outcome = $this->publisher->publishOutcome($context, $story, $scheduledAt);
                $article = $outcome->article;

                $isLive = $article !== null && $article->getStatus() === ArticleStatus::Published;
                $isScheduled = $article !== null && $article->getStatus() === ArticleStatus::Scheduled;
                $isDraft = $article !== null && $article->getStatus() === ArticleStatus::Draft;
                if ($isLive) {
                    ++$published;
                } elseif ($isScheduled) {
                    ++$scheduled;
                } elseif ($isDraft && $outcome->created) {
                    ++$drafts;
                }

                // Solo las que salen a cola pública consumen el escalonado.
                if ($outcome->created && ($isLive || $isScheduled)) {
                    $nextSlot = $this->advanceSlot($config, $scheduledAt ?? new \DateTimeImmutable());
                }

                $itemResults[] = new HimtecMediaFetchItemResult(
                    externalId: $story->getExternalId(),
                    title: $story->getTitle(),
                    outcome: $isDuplicate ? 'duplicate' : 'new',
                    ingestedStoryId: $story->getId(),
                    articleId: $article?->getId(),
                    published: $isLive,
                    scheduled: $isScheduled,
                    draft: $isDraft && $outcome->created,
                    scheduledAt: $isScheduled ? $article?->getScheduledAt() : null,
                );
            } catch (\Throwable $e) {
                ++$errors;
                $message = sprintf('Elemento #%d: %s', $index + 1, $e->getMessage());
                $errorMessages[] = $message;
                $itemResults[] = new HimtecMediaFetchItemResult(
                    externalId: $normalized !== null ? (string) ($normalized['id'] ?? '—') : $this->guessExternalId($item),
                    title: $normalized !== null ? (string) ($normalized['title'] ?? '—') : $this->guessTitle($item),
                    outcome: 'error',
                    error: $e->getMessage(),
                );
            }
        }

        $result = new HimtecMediaFetchResult(
            received: $received,
            duplicates: $duplicates,
            published: $published,
            scheduled: $scheduled,
            drafts: $drafts,
            errors: $errors,
            errorMessages: $errorMessages,
            feedTotal: count($items),
            items: $itemResults,
        );

        $run = $this->persistHistory($context, $result, $trigger, $requestUrl);
        $this->recordFetchResult($context, $result);

        return $result->withRunId($run->getId());
    }

    /** @return list<array<string, mixed>> */
    private function normalizePayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('El JSON debe ser un objeto o un array.');
        }

        if ($this->isList($payload)) {
            return $payload;
        }

        foreach (['noticias', 'items', 'stories', 'articles', 'news', 'data', 'results'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key]) && $this->isList($payload[$key])) {
                return $payload[$key];
            }
        }

        if (isset($payload['id']) && (isset($payload['title']) || isset($payload['titulo']) || isset($payload['titular']))) {
            return [$payload];
        }

        throw new \InvalidArgumentException(
            'Formato JSON no reconocido. Use un array de noticias o un objeto con clave "news", "items" o "data".',
        );
    }

    private function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }

    private function buildRequestUrl(string $jsonUrl): string
    {
        $parts = parse_url($jsonUrl);
        if ($parts === false) {
            return $jsonUrl;
        }

        parse_str($parts['query'] ?? '', $query);
        if (!isset($query['limit'])) {
            $query['limit'] = '10';
        }

        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $queryString = http_build_query($query);

        return sprintf('%s://%s%s%s?%s', $scheme, $host, $port, $path, $queryString);
    }

    private function recordFetchResult(SiteContext $context, HimtecMediaFetchResult $result): void
    {
        $status = $result->isSuccessful()
            ? $result->summary()
            : ($result->errorMessages[0] ?? $result->summary());

        $entries = [
            ['ingest', 'himtecmedia_last_fetch_status', $status],
            ['ingest', 'himtecmedia_last_fetch_count', $result->received],
            ['ingest', 'himtecmedia_last_fetch_duplicates', $result->duplicates],
        ];

        // Solo avanzar el reloj de periodicidad si la descarga/parseo fue OK.
        // Si falla la conexión, isDue() debe seguir en true para reintentar en el siguiente ciclo del scheduler.
        if ($result->isSuccessful()) {
            $entries[] = [
                'ingest',
                'himtecmedia_last_fetched_at',
                (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
        }

        $this->settingsWriter->setMany($context, $entries);
    }

    private function persistHistory(
        SiteContext $context,
        HimtecMediaFetchResult $result,
        string $trigger,
        ?string $requestUrl,
    ): \App\Entity\Platform\IngestFetchRun {
        return $this->historyRecorder->record($context, $result, $trigger, $requestUrl);
    }

    private function nextPublishSlot(SiteContext $context, HimtecMediaIngestConfig $config): \DateTimeImmutable
    {
        $now = new \DateTimeImmutable();
        if (!$config->usesStaggeredPublish()) {
            return $now;
        }

        $repo = $this->siteEmFactory->forContext($context)->getRepository(Article::class);
        $latest = $repo instanceof ArticleRepository ? $repo->findLatestIngestScheduledAt() : null;
        if ($latest !== null && $latest > $now) {
            return $latest->modify(sprintf('+%d minutes', $config->publishStaggerMinutes));
        }

        return $now;
    }

    private function consumePublishSlot(HimtecMediaIngestConfig $config, \DateTimeImmutable $nextSlot): ?\DateTimeImmutable
    {
        if (!$config->usesStaggeredPublish()) {
            return null;
        }

        return $nextSlot > new \DateTimeImmutable() ? $nextSlot : null;
    }

    private function advanceSlot(HimtecMediaIngestConfig $config, \DateTimeImmutable $from): \DateTimeImmutable
    {
        $minutes = max(1, $config->publishStaggerMinutes);

        return $from->modify(sprintf('+%d minutes', $minutes));
    }

    /** @param array<string, mixed> $item */
    private function guessTitle(array $item): string
    {
        foreach (['title', 'titulo', 'titular', 'headline', 'name'] as $key) {
            if (isset($item[$key]) && trim((string) $item[$key]) !== '') {
                return trim((string) $item[$key]);
            }
        }

        return '—';
    }

    private function translateFetchError(\Throwable $e, string $requestUrl): string
    {
        $message = $e->getMessage();

        if (preg_match('/Failed to connect|Couldn\'t connect to server|Connection refused|cURL error 7/i', $message)) {
            return sprintf(
                'No se puede conectar con HimtecMedia en %s. Asegúrate de que el backend HimtecMedia está en marcha (en himtecnews: docker compose up -d backend) y publicado en el puerto 8001 del host. Desde Docker la URL suele ser http://host.docker.internal:8001/api/public/medios/{id}/news (no uses el frontend en :3000). Si acabas de cambiar docker-compose.yml, recrea php/worker/scheduler: docker compose up -d --force-recreate php worker scheduler.',
                $requestUrl,
            );
        }

        if (preg_match('/401|Cabecera X-API-Key/i', $message)) {
            return 'HimtecMedia rechazó la petición: falta la cabecera X-API-Key. Revisa la API Key en esta pantalla.';
        }

        if (preg_match('/403|API key inválida|Medio no encontrado/i', $message)) {
            return 'HimtecMedia rechazó la API Key o el ID de medio en la URL no coincide. Copia la API Key del medio en HimtecMedia y verifica que el número en la URL (/medios/N/news) es el correcto.';
        }

        return $message;
    }

    /** @param array<string, mixed> $item */
    private function guessExternalId(array $item): string
    {
        foreach (['id', 'external_id', 'uuid', '_id', 'news_id'] as $key) {
            if (isset($item[$key]) && (string) $item[$key] !== '') {
                return (string) $item[$key];
            }
        }

        return '—';
    }
}
