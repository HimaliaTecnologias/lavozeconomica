<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Platform\Site;
use App\Ingestion\HimtecMedia\HimtecMediaFetchService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'ingest:himtecmedia:scheduled',
    description: 'Ejecuta ingesta HimtecMedia programada para todos los sitios que toque actualizar',
)]
final class IngestHimtecMediaScheduledCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteContextResolver $contextResolver,
        private readonly SiteSettingsReader $settingsReader,
        private readonly HimtecMediaFetchService $fetchService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sites = $this->platformEm->getRepository(Site::class)->findAll();
        $processed = 0;

        foreach ($sites as $site) {
            $context = $this->contextResolver->resolveByCode($site->getCode());
            if ($context === null) {
                continue;
            }

            $config = $this->settingsReader->getIngestConfig($context);
            if (!$config->isDue()) {
                continue;
            }

            try {
                $result = $this->fetchService->fetch($context, $config, 'scheduled');
                $io->writeln(sprintf(
                    '[%s] %s',
                    $site->getCode(),
                    $result->summary(),
                ));
                ++$processed;
            } catch (\Throwable $e) {
                $io->warning(sprintf('[%s] %s', $site->getCode(), $e->getMessage()));
            }
        }

        if ($processed === 0) {
            $io->note('Ningún sitio pendiente de actualización.');
        }

        return Command::SUCCESS;
    }
}
