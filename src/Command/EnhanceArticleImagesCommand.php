<?php

namespace App\Command;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Platform\Site;
use App\Entity\Site\ArticleImage;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Editorial\ArticleImageEnhancementDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:article-images:enhance',
    description: 'Encola mejora de calidad/resolución para imágenes de noticias por debajo del umbral editorial',
)]
final class EnhanceArticleImagesCommand extends Command
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly SiteContextResolver $siteResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly ArticleImageEnhancementDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('code', null, InputOption::VALUE_REQUIRED, 'Código del sitio')
            ->addOption('pending-only', null, InputOption::VALUE_NONE, 'Solo imágenes con estado pending')
            ->addOption('retry-failed', null, InputOption::VALUE_NONE, 'Reintentar imágenes con estado failed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $code = (string) $input->getOption('code');
        $pendingOnly = (bool) $input->getOption('pending-only');
        $retryFailed = (bool) $input->getOption('retry-failed');

        $sites = $code !== ''
            ? $this->loadSitesByCode($code)
            : $this->platformEm->getRepository(Site::class)->findAll();

        if ($sites === []) {
            $io->error('No se encontraron sitios.');

            return Command::FAILURE;
        }

        $totalQueued = 0;

        foreach ($sites as $siteEntity) {
            $context = $this->siteResolver->resolveByCode($siteEntity->getCode());
            if ($context === null) {
                continue;
            }

            $em = $this->siteEmFactory->forContext($context);
            $images = $em->getRepository(ArticleImage::class)->findAll();

            foreach ($images as $image) {
                if ($pendingOnly && $image->getEnhancementStatus() !== ArticleImageEnhancementStatus::Pending) {
                    continue;
                }

                if ($retryFailed && $image->getEnhancementStatus() === ArticleImageEnhancementStatus::Failed) {
                    $image->setEnhancementStatus(ArticleImageEnhancementStatus::Pending);
                    $image->setEnhancementError(null);
                }

                if ($image->getEnhancementStatus() !== ArticleImageEnhancementStatus::Pending) {
                    continue;
                }

                if ($this->dispatcher->dispatchForImage($context, $image)) {
                    ++$totalQueued;
                }
            }

            $em->flush();
        }

        $io->success(sprintf('Encoladas %d tareas de mejora de imagen.', $totalQueued));

        return Command::SUCCESS;
    }

    /** @return list<Site> */
    private function loadSitesByCode(string $code): array
    {
        $site = $this->platformEm->getRepository(Site::class)->findOneBy(['code' => $code]);
        if ($site === null) {
            return [];
        }

        return [$site];
    }
}
