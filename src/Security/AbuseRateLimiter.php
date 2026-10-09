<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Rate limiting helpers for public write/expensive endpoints.
 */
final class AbuseRateLimiter
{
    public function __construct(
        #[Target('ingest_api')]
        private readonly RateLimiterFactoryInterface $ingestApiLimiter,
        #[Target('public_form')]
        private readonly RateLimiterFactoryInterface $publicFormLimiter,
        #[Target('public_search')]
        private readonly RateLimiterFactoryInterface $publicSearchLimiter,
        #[Target('public_ugc')]
        private readonly RateLimiterFactoryInterface $publicUgcLimiter,
        #[Target('public_pdf')]
        private readonly RateLimiterFactoryInterface $publicPdfLimiter,
        #[Target('public_ad_click')]
        private readonly RateLimiterFactoryInterface $publicAdClickLimiter,
        #[Target('public_api_read')]
        private readonly RateLimiterFactoryInterface $publicApiReadLimiter,
    ) {}

    public function enforceIngest(Request $request): void
    {
        $this->consume($this->ingestApiLimiter, $this->clientKey($request, 'ingest'));
    }

    public function enforcePublicForm(Request $request, string $action): void
    {
        $this->consume($this->publicFormLimiter, $this->clientKey($request, 'form-'.$action));
    }

    public function enforceSearch(Request $request): void
    {
        $this->consume($this->publicSearchLimiter, $this->clientKey($request, 'search'));
    }

    public function enforceUgc(Request $request, string $action, ?string $userKey = null): void
    {
        $suffix = $userKey !== null && $userKey !== '' ? $userKey : $this->clientKey($request, 'ugc-'.$action);
        $this->consume($this->publicUgcLimiter, 'ugc-'.$action.'-'.$suffix);
    }

    public function enforcePdf(Request $request): void
    {
        $this->consume($this->publicPdfLimiter, $this->clientKey($request, 'pdf'));
    }

    public function enforceAdClick(Request $request): void
    {
        $this->consume($this->publicAdClickLimiter, $this->clientKey($request, 'ad'));
    }

    public function enforceApiRead(Request $request): void
    {
        $this->consume($this->publicApiReadLimiter, $this->clientKey($request, 'api'));
    }

    private function consume(RateLimiterFactoryInterface $factory, string $key): void
    {
        $limit = $factory->create($key)->consume(1);
        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter();
        $seconds = max(1, $retryAfter->getTimestamp() - time());

        throw new TooManyRequestsHttpException($seconds, 'Demasiadas peticiones. Inténtalo de nuevo más tarde.');
    }

    private function clientKey(Request $request, string $bucket): string
    {
        $ip = $request->getClientIp() ?? 'unknown';

        return $bucket.'-'.$ip;
    }
}
