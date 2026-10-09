<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Platform\Site;
use App\Entity\Site\Article;
use App\Editorial\ArticleTagSyncService;
use App\Repository\Site\ArticleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'articles:retag', description: 'Regenera etiquetas automáticas de las noticias')]
final class RetagArticlesCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly \Doctrine\ORM\EntityManagerInterface $platformEm,
        private readonly SiteContextResolver $siteResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly ArticleTagSyncService $tagSyncService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('code', null, InputOption::VALUE_REQUIRED, 'Código del periódico (todos si se omite)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $codeFilter = $input->getOption('code');
        $sites = $this->platformEm->getRepository(Site::class)->findAll();
        $total = 0;

        foreach ($sites as $site) {
            if ($codeFilter !== null && $site->getCode() !== $codeFilter) {
                continue;
            }

            $context = $this->siteResolver->resolveByCode($site->getCode());
            if ($context === null) {
                continue;
            }

            $em = $this->siteEmFactory->forContext($context);
            $repo = $em->getRepository(Article::class);
            if (!$repo instanceof ArticleRepository) {
                continue;
            }

            $articles = $repo->findBy([], ['id' => 'ASC']);
            foreach ($articles as $article) {
                $this->tagSyncService->syncForArticle($em, $article);
                $em->flush();
                ++$total;
            }

            $io->writeln(sprintf('  %s: %d noticia(s)', $site->getCode(), count($articles)));
        }

        $io->success(sprintf('Etiquetas regeneradas en %d noticia(s).', $total));

        return Command::SUCCESS;
    }
}
