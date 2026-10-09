<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;
use App\Tv\TvProgrammingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicTvController extends AbstractController
{
    /** @var list<string> */
    private const TV_TAG_SLUGS = ['television', 'tv', 'series', 'cine', 'entretenimiento'];

    #[Route('/guia-tv', name: 'public_tv', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        TvProgrammingService $tvService,
    ): Response {
        $site = $holder->require();
        $config = $tvService->getConfig($site);

        if (!$config->enabled || !$config->isConfigured()) {
            throw $this->createNotFoundException('La guía de televisión no está disponible.');
        }

        $pageContext = $pageContextFactory->build($site);
        $schedule = $tvService->getSchedule($site);

        $em = $siteEmFactory->forContext($site);
        $articleRepo = $em->getRepository(Article::class);
        $tvArticles = [];
        $latestArticles = [];

        if ($articleRepo instanceof ArticleRepository) {
            $sidebarLimit = max(3, min(12, (int) ($pageContext['layout']['sidebarLatestCount'] ?? 5)));
            $tvArticles = $articleRepo->findPublishedByAnyTagSlug(self::TV_TAG_SLUGS, 8);
            $latestArticles = $articleRepo->findPublishedLatest($sidebarLimit);
        }

        $newsArticles = $tvArticles !== [] ? $tvArticles : $latestArticles;
        $nowOnTv = $schedule?->nowOnTv() ?? [];

        return $this->render('public/tv/index.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'config' => $config,
            'schedule' => $schedule,
            'nowOnTv' => $nowOnTv,
            'tvArticles' => $newsArticles,
            'sidebarArticles' => $latestArticles,
            'seoTitle' => 'Guía TV y programación — '.$site->name,
            'seoDescription' => 'Programación de televisión de los principales canales en '.$site->name,
        ]);
    }
}
