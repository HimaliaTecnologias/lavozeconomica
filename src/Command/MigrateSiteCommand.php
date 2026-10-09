<?php

namespace App\Command;

use App\Core\Site\SiteProvisioner;
use App\Entity\Platform\Site;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'migrate:site', description: 'Ejecuta migraciones en la BD de un periódico')]
final class MigrateSiteCommand extends Command
{
    public function __construct(
        private readonly SiteProvisioner $siteProvisioner,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly \Doctrine\ORM\EntityManagerInterface $platformEm,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('code', null, InputOption::VALUE_REQUIRED, 'Código del sitio');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = (string) $input->getOption('code');

        try {
            $this->siteProvisioner->migrateSiteSchema($code);
        } catch (\Throwable $e) {
            $hint = $this->connectionHint($code);
            $io->error(trim($e->getMessage().($hint !== '' ? " ({$hint})" : '')));
            $io->writeln(sprintf('<comment>at %s:%d</comment>', $e->getFile(), $e->getLine()));
            $previous = $e->getPrevious();
            if ($previous !== null) {
                $io->writeln(sprintf(
                    '<comment>previous: %s — %s</comment>',
                    $previous::class,
                    $previous->getMessage(),
                ));
            }
            if ($output->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }

        $io->success("Schema site actualizado para '{$code}'.");

        return Command::SUCCESS;
    }

    private function connectionHint(string $code): string
    {
        $site = $this->platformEm->getRepository(Site::class)->findOneBy(['code' => $code]);
        $config = $site?->getDatabaseConfig();
        if ($config === null) {
            return '';
        }

        return sprintf(
            'config=%s@%s:%d/%s',
            $config->getUsername(),
            $config->getHost(),
            $config->getPort(),
            $config->getDatabaseName(),
        );
    }
}
