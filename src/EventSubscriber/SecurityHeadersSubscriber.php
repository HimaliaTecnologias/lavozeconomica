<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // After Symfony's DisallowRobotsIndexingListener (-255), which adds
        // X-Robots-Tag: noindex when kernel.debug is true.
        return [KernelEvents::RESPONSE => ['onResponse', -256]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;
        $path = $event->getRequest()->getPathInfo();

        if ($this->isPrivatePath($path)) {
            $headers->set('X-Robots-Tag', 'noindex, nofollow');
        } elseif ($path === '/robots.txt') {
            // El controller puede fijar noindex; no lo pisa Symfony debug.
            if (!$headers->has('X-Robots-Tag')) {
                $headers->set('X-Robots-Tag', 'noindex');
            }
        } else {
            // Quita el X-Robots-Tag: noindex que Symfony añade con kernel.debug.
            $headers->remove('X-Robots-Tag');
        }

        if (!$headers->has('X-Content-Type-Options')) {
            $headers->set('X-Content-Type-Options', 'nosniff');
        }
        if (!$headers->has('X-Frame-Options')) {
            $headers->set('X-Frame-Options', 'SAMEORIGIN');
        }
        if (!$headers->has('Referrer-Policy')) {
            $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        if (!$headers->has('Permissions-Policy')) {
            $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        }
        if (!$headers->has('Cross-Origin-Opener-Policy')) {
            $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        }

        // Public sites may inject Matomo/GA and third-party embeds (Stay22, webcams…).
        // Keep object-src locked down; allow HTTPS for scripts, frames and XHR beacons.
        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "img-src 'self' data: https: blob:",
                "media-src 'self' https: blob:",
                "font-src 'self' data: https:",
                "style-src 'self' 'unsafe-inline' https:",
                "script-src 'self' 'unsafe-inline' https:",
                "frame-src 'self' https:",
                "connect-src 'self' https:",
            ]));
        }
    }

    private function isPrivatePath(string $path): bool
    {
        foreach (['/gestor', '/admin', '/panel', '/cuenta', '/install'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }
}
