<?php

namespace App\Command;

use App\Core\Site\SiteProvisioner;
use App\Entity\Platform\Site;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'migrate:sites', description: 'Ejecuta migraciones site en todos los periódicos')]
final class MigrateSitesCommand extends Command
{
    public function __construct(
        private readonly SiteProvisioner $siteProvisioner,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly \Doctrine\ORM\EntityManagerInterface $platformEm,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sites = $this->platformEm->getRepository(Site::class)->findAll();

        foreach ($sites as $site) {
            $code = $site->getCode();
            $io->writeln("Migrando site: {$code}");
            try {
                $this->siteProvisioner->migrateSiteSchema($code);
                $io->writeln("  OK {$code}");
            } catch (\Throwable $e) {
                $io->error("Fallo en {$code}: ".$e->getMessage());
                $io->writeln(sprintf('<comment>at %s:%d</comment>', $e->getFile(), $e->getLine()));
                if ($output->isVerbose()) {
                    $io->writeln($e->getTraceAsString());
                }

                return Command::FAILURE;
            }
        }

        $io->success('Todos los sitios migrados.');

        return Command::SUCCESS;
    }
}
