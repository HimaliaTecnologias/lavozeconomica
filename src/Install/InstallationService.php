<?php

namespace App\Install;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteProvisioner;
use App\Entity\Platform\Site;
use App\Entity\Platform\User;
use App\Entity\Platform\UserSiteRole;
use App\Entity\Site\SiteSetting;
use App\Install\Dto\InstallWizardData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InstallationService
{
    public function __construct(
        private readonly InstallationStateService $stateService,
        private readonly SiteProvisioner $siteProvisioner,
        private readonly SiteContextResolver $contextResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly UserPasswordHasherInterface $passwordHasher,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {}

    public function needsInstallation(): bool
    {
        return $this->stateService->needsInstallation();
    }

    public function install(InstallWizardData $data): Site
    {
        if (!$this->needsInstallation()) {
            throw new \RuntimeException('La plataforma ya está instalada.');
        }

        $site = $this->siteProvisioner->provision(
            code: $data->code,
            name: $data->name,
            domain: $data->domain,
            locale: $data->locale,
            timezone: $data->timezone,
        );

        $this->saveSiteSettings($site, $data);

        $user = new User(
            email: $data->adminEmail,
            name: $data->adminName,
            passwordHash: '',
            isPlatformSuperadmin: true,
        );
        $user->setPasswordHash(
            $this->passwordHasher->hashPassword($user, $data->adminPassword),
        );
        $user->markEmailVerified();
        $this->platformEm->persist($user);
        $this->platformEm->persist(new UserSiteRole($user, $site, 'site_admin'));
        $this->platformEm->flush();

        $this->stateService->markInstalled($site->getCode());

        return $site;
    }

    private function saveSiteSettings(Site $site, InstallWizardData $data): void
    {
        $context = $this->contextResolver->resolveByCode($site->getCode());
        if ($context === null) {
            throw new \RuntimeException('Contexto de sitio no disponible tras la instalación.');
        }

        $siteEm = $this->siteEmFactory->forContext($context);

        $settings = [
            ['theme', 'primary_color', ['value' => $data->primaryColor]],
            ['theme', 'secondary_color', ['value' => $data->secondaryColor]],
            ['identity', 'tagline', ['value' => $data->tagline]],
            ['seo', 'meta_description', ['value' => $data->tagline]],
        ];

        foreach ($settings as [$namespace, $key, $value]) {
            $siteEm->persist(new SiteSetting($namespace, $key, $value));
        }

        $siteEm->flush();
    }
}
