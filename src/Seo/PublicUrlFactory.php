<?php

namespace App\Seo;

use App\Core\Site\SiteContextHolder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * URLs públicas canónicas: https://www.lavozeconomica.com (Plesk redirige el apex a www).
 */
final class PublicUrlFactory
{
    public function __construct(
        private readonly SiteContextHolder $siteHolder,
        private readonly RequestStack $requestStack,
        private readonly RequestContextAwareInterface $router,
        private readonly string $publicBaseUrl,
    ) {}

    public function canonicalHost(): string
    {
        $envHost = $this->hostFromAbsolute($this->publicBaseUrl);
        $siteApex = $this->apex($this->siteHolder->get()?->domain ?? '');

        if ($envHost !== '') {
            if ($siteApex === '' || $this->isEphemeralHost($siteApex) || $this->apex($envHost) === $siteApex) {
                return $envHost;
            }
        }

        if ($siteApex !== '' && !$this->isEphemeralHost($siteApex)) {
            return str_starts_with($envHost, 'www.') ? 'www.'.$siteApex : $siteApex;
        }

        if ($envHost !== '') {
            return $envHost;
        }

        $request = $this->requestStack->getCurrentRequest();
        $fromRequest = strtolower(preg_replace('/:\d+$/', '', $request?->getHost() ?? 'localhost') ?? 'localhost');

        return $fromRequest !== '' ? $fromRequest : 'www.lavozeconomica.com';
    }

    public function normalize(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $this->absolutePath('/');
        }

        if (!preg_match('#^https?://#i', $url)) {
            return $this->absolutePath($url);
        }

        $path = parse_url($url, PHP_URL_PATH);
        $path = \is_string($path) && $path !== '' ? $path : '/';

        return $this->absolutePath($path);
    }

    public function absolutePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/') {
            $path = '/'.$path;
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return 'https://'.$this->canonicalHost().$path;
    }

    public function applyRouterContext(): void
    {
        $host = $this->canonicalHost();
        $context = $this->router->getContext();
        $context->setScheme('https');
        $context->setHost($host);
        $context->setHttpPort(80);
        $context->setHttpsPort(443);
    }

    public function apex(string $host): string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? trim($host));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public function isLocalHost(string $host): bool
    {
        $host = $this->apex($host);

        return $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local');
    }

    public function isEphemeralHost(string $host): bool
    {
        $host = $this->apex($host);

        return $this->isLocalHost($host)
            || str_ends_with($host, '.ddns.net')
            || filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    private function hostFromAbsolute(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return '';
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            return '';
        }

        return strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
    }
}
