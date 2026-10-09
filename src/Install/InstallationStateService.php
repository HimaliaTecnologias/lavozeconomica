<?php

namespace App\Install;

use App\Entity\Platform\PlatformState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class InstallationStateService
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {}

    public function needsInstallation(): bool
    {
        return !$this->getState()->isInstalled();
    }

    public function getState(): PlatformState
    {
        $state = $this->platformEm->find(PlatformState::class, 1);
        if ($state === null) {
            $state = new PlatformState();
            $this->platformEm->persist($state);
            $this->platformEm->flush();
        }

        return $state;
    }

    public function markInstalled(string $primarySiteCode): void
    {
        $state = $this->getState();
        $state->markInstalled($primarySiteCode);
        $this->platformEm->flush();
    }
}
