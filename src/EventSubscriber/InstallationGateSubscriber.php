<?php

namespace App\EventSubscriber;

use App\Install\InstallationStateService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class InstallationGateSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly InstallationStateService $installationState,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 200],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if ($this->isAllowedDuringInstall($path)) {
            return;
        }

        try {
            if (!$this->installationState->needsInstallation()) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $event->setResponse(new RedirectResponse(
            $this->urlGenerator->generate('install_welcome'),
        ));
    }

    private function isAllowedDuringInstall(string $path): bool
    {
        return str_starts_with($path, '/install')
            || str_starts_with($path, '/health');
    }
}
