<?php

namespace App\EventSubscriber;

use App\Seo\PublicUrlFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Un host canónico (www, el que usa Plesk) y URLs HTTPS en sitemap/canónicas.
 */
final class CanonicalHostRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PublicUrlFactory $publicUrls,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['applyPublicUrls', 90],
        ];
    }

    public function applyPublicUrls(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->publicUrls->applyRouterContext();

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (str_starts_with($path, '/health') || str_starts_with($path, '/install')) {
            return;
        }

        $current = strtolower(preg_replace('/:\d+$/', '', $request->getHost()) ?? $request->getHost());
        if ($this->publicUrls->isLocalHost($current)) {
            return;
        }

        $canonical = $this->publicUrls->canonicalHost();
        if ($canonical === '' || $current === $canonical) {
            return;
        }

        $status = \in_array($request->getMethod(), ['GET', 'HEAD'], true) ? 301 : 308;
        $event->setResponse(new RedirectResponse('https://'.$canonical.$request->getRequestUri(), $status));
    }
}
