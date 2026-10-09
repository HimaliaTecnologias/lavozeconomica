<?php

namespace App\Command;

use App\Cache\PublicArticleCache;
use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Editorial\ArticleFeaturedImageGuard;
use App\Entity\Platform\Site;
use App\Entity\Site\Enum\ArticleStatus;
use App\Repository\Site\ArticleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'articles:publish-scheduled', description: 'Publica noticias programadas cuya fecha ya ha llegado')]
final class PublishScheduledArticlesCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly \Doctrine\ORM\EntityManagerInterface $platformEm,
        private readonly SiteContextResolver $siteResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly PublicArticleCache $articleCache,
        private readonly ArticleFeaturedImageGuard $featuredImageGuard,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sites = $this->platformEm->getRepository(Site::class)->findAll();
        $total = 0;
        $skipped = 0;

        foreach ($sites as $site) {
            $context = $this->siteResolver->resolveByCode($site->getCode());
            if ($context === null) {
                continue;
            }

            $em = $this->siteEmFactory->forContext($context);
            $repo = $em->getRepository(\App\Entity\Site\Article::class);
            if (!$repo instanceof ArticleRepository) {
                continue;
            }

            try {
                $publishedHere = 0;
                foreach ($repo->findScheduledDue() as $article) {
                    if (!$this->featuredImageGuard->hasUsableHeroVisual($article)) {
                        // No publicar sin imagen usable: vuelve a borrador para revisión.
                        $article->setStatus(ArticleStatus::Draft);
                        $article->setScheduledAt(null);
                        ++$skipped;
                        $io->writeln(sprintf(
                            '[%s] omitida «%s»: %s',
                            $context->code,
                            mb_substr($article->getTitle(), 0, 80),
                            $this->featuredImageGuard->getIssue($article) ?? 'sin imagen usable',
                        ));
                        continue;
                    }

                    $article->publish();
                    ++$publishedHere;
                    ++$total;
                }

                $em->flush();
                if ($publishedHere > 0) {
                    $this->articleCache->invalidateSite($context);
                }
            } catch (\Throwable $e) {
                $io->warning(sprintf('[%s] No se pudieron publicar programadas: %s', $context->code, $e->getMessage()));
            }
        }

        $io->success(sprintf(
            'Publicadas %d noticia(s) programada(s)%s.',
            $total,
            $skipped > 0 ? sprintf(', %d omitida(s) sin imagen', $skipped) : '',
        ));

        return Command::SUCCESS;
    }
}
