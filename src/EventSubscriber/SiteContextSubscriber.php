<?php

namespace App\EventSubscriber;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteContextResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SiteContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SiteContextResolver $resolver,
        private readonly SiteContextHolder $holder,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/health')) {
            return;
        }

        $context = $this->resolver->resolveByDomain($request->getHost());
        if ($context !== null) {
            $this->holder->set($context);
            $request->attributes->set('_site', $context);
        }
    }
}
