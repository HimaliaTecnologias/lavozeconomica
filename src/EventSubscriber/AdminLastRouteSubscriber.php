<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest')]
final class AdminLastRouteSubscriber
{
    private const SESSION_ROUTE = 'admin_last_route';
    private const SESSION_PARAMS = 'admin_last_route_params';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/gestor')) {
            return;
        }

        if ($request->attributes->get('_route') === 'admin_login') {
            return;
        }

        if ($this->tokenStorage->getToken()?->getUser() === null) {
            return;
        }

        $route = $request->attributes->get('_route');
        if (!is_string($route) || $route === '') {
            return;
        }

        $session = $request->getSession();
        $session->set(self::SESSION_ROUTE, $route);
        $params = $request->attributes->get('_route_params');
        $session->set(self::SESSION_PARAMS, is_array($params) ? $params : []);
    }

    public static function sessionRouteKey(): string
    {
        return self::SESSION_ROUTE;
    }

    public static function sessionParamsKey(): string
    {
        return self::SESSION_PARAMS;
    }
}
