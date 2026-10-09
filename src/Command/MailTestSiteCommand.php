<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Editorial\SiteMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mail:test-site',
    description: 'Envía un email de prueba con la configuración SMTP del periódico',
)]
final class MailTestSiteCommand extends Command
{
    public function __construct(
        private readonly SiteContextResolver $contextResolver,
        private readonly SiteMailer $siteMailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Email de destino')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Código del periódico', 'demo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = (string) $input->getOption('site');
        $to = trim((string) $input->getArgument('to'));

        $site = $this->contextResolver->resolveByCode($code);
        if ($site === null) {
            $io->error(sprintf('Periódico "%s" no encontrado.', $code));

            return Command::FAILURE;
        }

        if (!$this->siteMailer->isOperational($site)) {
            $io->error('SMTP no está configurado/activado para este periódico (Configuración → Correo).');

            return Command::FAILURE;
        }

        $io->writeln(sprintf('Enviando prueba a %s (sitio %s)…', $to, $site->name));

        try {
            $this->siteMailer->sendTestMessage($site, $to);
        } catch (\Throwable $e) {
            $io->error('Falló el envío: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Email de prueba enviado correctamente.');

        return Command::SUCCESS;
    }
}
