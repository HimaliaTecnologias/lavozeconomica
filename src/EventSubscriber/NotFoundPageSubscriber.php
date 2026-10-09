<?php

namespace App\EventSubscriber;

use App\Core\Site\Error404PageRenderer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class NotFoundPageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Error404PageRenderer $renderer,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -32],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();
        if (!$throwable instanceof NotFoundHttpException) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/gestor')
            || str_starts_with($path, '/admin')
            || str_starts_with($path, '/panel')
            || str_starts_with($path, '/_')
            || str_starts_with($path, '/health')
        ) {
            return;
        }

        $response = $this->renderer->render($path);
        if ($response === null) {
            return;
        }

        $event->setResponse($response);
    }
}
