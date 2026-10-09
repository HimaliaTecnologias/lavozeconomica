<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Editorial\DemoContentSeeder;
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
    name: 'app:bootstrap-demo',
    description: 'Crea categorías y noticias de ejemplo para un periódico',
)]
final class BootstrapDemoCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteContextResolver $resolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly DemoContentSeeder $demoContentSeeder,
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

        $result = $this->demoContentSeeder->seed($context, $this->siteEmFactory->forContext($context));

        if ($result['created'] === 0 && $result['repaired'] === 0) {
            $io->note(sprintf(
                'El sitio "%s" ya tiene las noticias de ejemplo. No se crearon duplicados.',
                $code,
            ));
        } else {
            $io->success(sprintf(
                'Contenido demo en "%s": %d noticias nuevas, %d categorías disponibles, %d artículos reparados.',
                $code,
                $result['created'],
                $result['categories'],
                $result['repaired'],
            ));
        }

        return Command::SUCCESS;
    }
}
