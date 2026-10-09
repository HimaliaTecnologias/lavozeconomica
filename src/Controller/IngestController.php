<?php

namespace App\Controller;

use App\Ingestion\HimtecMedia\HimtecMediaIngestService;
use App\Security\AbuseRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class IngestController extends AbstractController
{
    private const MAX_PAYLOAD_BYTES = 524_288; // 512 KiB

    public function __construct(
        private readonly AbuseRateLimiter $abuseRateLimiter,
        #[Autowire(env: 'INGEST_API_KEY')]
        private readonly string $ingestApiKey = '',
    ) {}

    #[Route('/api/v1/ingest/himtecmedia', name: 'api_ingest_himtecmedia', methods: ['POST'])]
    public function himtecmedia(Request $request, HimtecMediaIngestService $service): JsonResponse
    {
        if (!$this->isAuthorized($request)) {
            return new JsonResponse(['error' => 'No autorizado'], 401);
        }

        $this->abuseRateLimiter->enforceIngest($request);

        $raw = $request->getContent();
        if (strlen($raw) > self::MAX_PAYLOAD_BYTES) {
            return new JsonResponse(['error' => 'Payload demasiado grande'], 413);
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'JSON inválido'], 400);
        }

        try {
            $story = $service->ingest($payload);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        return new JsonResponse([
            'id' => $story->getId(),
            'external_id' => $story->getExternalId(),
            'status' => $story->getStatus()->value,
        ], 202);
    }

    private function isAuthorized(Request $request): bool
    {
        $expected = trim($this->ingestApiKey);
        if ($expected === '') {
            // Fail closed: without a configured key the endpoint rejects all traffic.
            return false;
        }

        $provided = $this->extractApiKey($request);
        if ($provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }

    private function extractApiKey(Request $request): string
    {
        $header = trim((string) $request->headers->get('X-API-Key', ''));
        if ($header !== '') {
            return $header;
        }

        $auth = trim((string) $request->headers->get('Authorization', ''));
        if (preg_match('/^Bearer\s+(\S+)/i', $auth, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
