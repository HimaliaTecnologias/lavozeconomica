<?php

namespace App\Core\Site;

use App\Entity\Site\SiteSetting;

final class SiteSettingsWriter
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {}

    public function set(SiteContext $context, string $namespace, string $key, mixed $value): void
    {
        $em = $this->siteEmFactory->forContext($context);
        $setting = $em->getRepository(SiteSetting::class)->findOneBy([
            'namespace' => $namespace,
            'keyName' => $key,
        ]);

        if ($setting === null) {
            $setting = new SiteSetting($namespace, $key, ['value' => $value]);
            $em->persist($setting);
        } else {
            $setting->setValue(['value' => $value]);
        }

        $em->flush();
    }

    /**
     * @param list<array{0: string, 1: string, 2: mixed}> $entries [namespace, key, value]
     */
    public function setMany(SiteContext $context, array $entries): void
    {
        $em = $this->siteEmFactory->forContext($context);
        $repo = $em->getRepository(SiteSetting::class);

        foreach ($entries as [$namespace, $key, $value]) {
            $setting = $repo->findOneBy(['namespace' => $namespace, 'keyName' => $key]);
            if ($setting === null) {
                $em->persist(new SiteSetting($namespace, $key, ['value' => $value]));
            } else {
                $setting->setValue(['value' => $value]);
            }
        }

        $em->flush();
    }
}
