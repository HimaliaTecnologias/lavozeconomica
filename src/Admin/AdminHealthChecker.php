<?php

namespace App\Admin;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Site\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AdminHealthChecker
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly SiteSettingsReader $settingsReader,
        #[Autowire(env: 'MAILER_DSN')]
        private readonly string $mailerDsn,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /** @return list<array{label: string, status: string, detail: string}> */
    public function check(SiteContext $site): array
    {
        $checks = [];

        $weather = $this->settingsReader->getWeatherConfig($site);
        $checks[] = [
            'label' => 'Sección del tiempo',
            'status' => $weather->enabled && $weather->postalCode !== '' ? 'ok' : 'warn',
            'detail' => $weather->enabled
                ? ($weather->postalCode !== '' ? 'Activa · CP '.$weather->postalCode : 'Falta código postal')
                : 'Desactivada',
        ];

        $ingest = $this->settingsReader->getIngestConfig($site);
        $checks[] = [
            'label' => 'Ingesta HimtecMedia',
            'status' => $ingest->enabled && $ingest->jsonUrl !== '' ? 'ok' : 'warn',
            'detail' => $ingest->enabled
                ? ($ingest->jsonUrl !== '' ? 'Automática configurada' : 'URL JSON pendiente')
                : 'Desactivada',
        ];

        $mailConfig = $this->settingsReader->getMailConfig($site);
        $mailOk = $mailConfig->isConfigured() || !str_starts_with($this->mailerDsn, 'null://');
        $checks[] = [
            'label' => 'Correo (SMTP)',
            'status' => $mailOk ? 'ok' : 'warn',
            'detail' => $mailConfig->isConfigured()
                ? 'SMTP del sitio: '.$mailConfig->host
                : ($mailOk ? 'MAILER_DSN global' : 'Configura SMTP en Ajustes → Correo'),
        ];

        $em = $this->siteEmFactory->forContext($site);
        $mediaCount = (int) $em->getRepository(Media::class)->count([]);
        $checks[] = [
            'label' => 'Biblioteca de medios',
            'status' => 'ok',
            'detail' => sprintf('%d archivos en biblioteca', $mediaCount),
        ];

        $mediaDir = $this->projectDir.'/public/media';
        $mediaWritable = is_dir($mediaDir) && is_writable($mediaDir);
        $checks[] = [
            'label' => 'Permisos de subida',
            'status' => $mediaWritable ? 'ok' : 'error',
            'detail' => $mediaWritable
                ? 'public/media escribible'
                : 'PHP-FPM no puede crear carpetas en public/media. El directorio debe pertenecer a www-data (reinicia el contenedor php tras desplegar).',
        ];

        return $checks;
    }
}
