<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Ingestion\HimtecMedia\HimtecMediaFetchService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ingest:himtecmedia:fetch',
    description: 'Descarga e ingiere historias HimtecMedia desde la URL JSON configurada para un sitio',
)]
final class IngestHimtecMediaFetchCommand extends Command
{
    public function __construct(
        private readonly SiteContextResolver $contextResolver,
        private readonly HimtecMediaFetchService $fetchService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('code', null, InputOption::VALUE_REQUIRED, 'Código del periódico');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = (string) $input->getOption('code');

        if ($code === '') {
            $io->error('Indica el código del sitio con --code=demo');

            return Command::FAILURE;
        }

        $context = $this->contextResolver->resolveByCode($code);
        if ($context === null) {
            $io->error(sprintf("Sitio '%s' no encontrado.", $code));

            return Command::FAILURE;
        }

        try {
            $result = $this->fetchService->fetch($context, null, 'command');
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$result->isSuccessful()) {
            $io->warning($result->errorMessages[0] ?? $result->summary());
        } else {
            $io->success($result->summary());
        }

        return Command::SUCCESS;
    }
}
