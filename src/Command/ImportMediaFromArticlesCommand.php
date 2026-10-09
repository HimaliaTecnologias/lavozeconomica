<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Editorial\MediaLibraryImportService;
use App\Entity\Platform\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'media:import-from-articles',
    description: 'Importa a la biblioteca las imágenes usadas en noticias sin registro en media',
)]
final class ImportMediaFromArticlesCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteContextResolver $resolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly MediaLibraryImportService $importService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('code', null, InputOption::VALUE_REQUIRED, 'Código del periódico', 'demo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = strtolower(trim((string) $input->getOption('code')));

        $site = $this->platformEm->getRepository(Site::class)->findOneBy(['code' => $code]);
        if ($site === null) {
            $io->error(sprintf('Sitio "%s" no encontrado.', $code));

            return Command::FAILURE;
        }

        $context = $this->resolver->resolveByCode($code);
        if ($context === null) {
            $io->error('No se pudo resolver el contexto del sitio.');

            return Command::FAILURE;
        }

        $em = $this->siteEmFactory->forContext($context);

        $pending = $this->importService->countUnlinkedArticleImages($em);
        if ($pending === 0) {
            $io->success('No hay imágenes de noticias pendientes de importar.');

            return Command::SUCCESS;
        }

        $result = $this->importService->importFromArticleImages($em);
        $io->success(sprintf(
            'Importadas %d nuevas, %d vinculadas, %d omitidas (pendientes antes: %d).',
            $result['imported'],
            $result['linked'],
            $result['skipped'],
            $pending,
        ));

        return Command::SUCCESS;
    }
}
