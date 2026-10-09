<?php

namespace App\Command;

use App\Core\Site\SiteProvisioner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'site:create', description: 'Crea un periódico: BD dedicada, registro en plataforma y migraciones site')]
final class SiteCreateCommand extends Command
{
    public function __construct(
        private readonly SiteProvisioner $siteProvisioner,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Código único del sitio')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nombre del periódico')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Dominio principal')
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Locale', 'es')
            ->addOption('timezone', null, InputOption::VALUE_OPTIONAL, 'Timezone', 'Europe/Madrid');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $site = $this->siteProvisioner->provision(
                code: (string) $input->getOption('code'),
                name: (string) $input->getOption('name'),
                domain: (string) $input->getOption('domain'),
                locale: (string) $input->getOption('locale'),
                timezone: (string) $input->getOption('timezone'),
            );
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success("Periódico '{$site->getCode()}' listo.");

        return Command::SUCCESS;
    }
}
