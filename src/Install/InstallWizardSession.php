<?php

namespace App\Install;

use App\Install\Dto\InstallWizardData;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class InstallWizardSession
{
    private const SESSION_KEY = 'install_wizard';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {}

    public function get(): InstallWizardData
    {
        $session = $this->getSession();
        $data = $session->get(self::SESSION_KEY, []);

        return InstallWizardData::fromArray(is_array($data) ? $data : []);
    }

    public function store(InstallWizardData $data): void
    {
        $this->getSession()->set(self::SESSION_KEY, $data->toArray());
    }

    public function merge(array $payload): InstallWizardData
    {
        $current = $this->get()->toArray();
        $merged = InstallWizardData::fromArray(array_merge($current, $payload));
        $this->store($merged);

        return $merged;
    }

    public function clear(): void
    {
        $this->getSession()->remove(self::SESSION_KEY);
    }

    private function getSession(): SessionInterface
    {
        $session = $this->requestStack->getSession();

        if (!$session->isStarted()) {
            $session->start();
        }

        return $session;
    }
}
