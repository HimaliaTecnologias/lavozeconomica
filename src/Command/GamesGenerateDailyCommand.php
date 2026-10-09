<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Entity\Platform\Site;
use App\Games\GamesService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'games:generate-daily',
    description: 'Genera (si falta) el sudoku y el anagrama del día en los periódicos con pasatiempos activos',
)]
final class GamesGenerateDailyCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteContextResolver $contextResolver,
        private readonly GamesService $gamesService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Regenera los retos aunque ya existan para hoy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $sites = $this->platformEm->getRepository(Site::class)->findAll();
        $processed = 0;

        foreach ($sites as $site) {
            $context = $this->contextResolver->resolveByCode($site->getCode());
            if ($context === null) {
                continue;
            }

            $config = $this->gamesService->getConfig($context);
            if (!$config->isConfigured()) {
                continue;
            }

            try {
                $parts = [];
                if ($config->sudokuEnabled) {
                    $sudoku = $this->gamesService->getDailySudoku($context, $force);
                    $parts[] = $sudoku !== null ? 'sudoku=ok' : 'sudoku=skip';
                }
                if ($config->wordScrambleEnabled) {
                    $word = $this->gamesService->getDailyWordScramble($context, $force);
                    $parts[] = $word !== null ? 'anagrama=ok' : 'anagrama=skip';
                }
                if ($parts === []) {
                    continue;
                }
                $io->writeln(sprintf('[%s] %s', $site->getCode(), implode(' · ', $parts)));
                ++$processed;
            } catch (\Throwable $e) {
                $io->warning(sprintf('[%s] %s', $site->getCode(), $e->getMessage()));
            }
        }

        if ($processed === 0) {
            $io->note('Ningún periódico con pasatiempos activos.');
        }

        return Command::SUCCESS;
    }
}
