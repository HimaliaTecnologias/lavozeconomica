<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Markets\MarketAiSummaryService;
use App\Markets\MarketService;
use App\Repository\Site\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicMarketController extends AbstractController
{
    /** @var list<string> */
    private const ECONOMY_TAG_SLUGS = ['economia', 'bolsa', 'ibex', 'mercados', 'finanzas'];

    #[Route('/bolsa', name: 'public_markets', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        MarketService $marketService,
        MarketAiSummaryService $marketAiSummaryService,
    ): Response {
        $site = $holder->require();
        $config = $marketService->getConfig($site);

        if (!$config->enabled || !$config->isConfigured()) {
            throw $this->createNotFoundException('La sección de bolsa no está disponible.');
        }

        $pageContext = $pageContextFactory->build($site);
        $board = $marketService->getBoard($site);
        $aiSummary = $board !== null
            ? $marketAiSummaryService->getSummary($site, $board)
            : null;

        $em = $siteEmFactory->forContext($site);
        $articleRepo = $em->getRepository(Article::class);
        $economyArticles = [];
        $latestArticles = [];

        if ($articleRepo instanceof ArticleRepository) {
            $sidebarLimit = max(3, min(12, (int) ($pageContext['layout']['sidebarLatestCount'] ?? 5)));
            $economyArticles = $articleRepo->findPublishedByAnyTagSlug(self::ECONOMY_TAG_SLUGS, 8);
            $latestArticles = $articleRepo->findPublishedLatest($sidebarLimit);
        }

        $newsArticles = $economyArticles !== [] ? $economyArticles : $latestArticles;

        return $this->render('public/markets/index.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'config' => $config,
            'board' => $board,
            'headline' => $board?->headline(),
            'sentiment' => $board?->sentiment() ?? ['up' => 0, 'down' => 0, 'flat' => 0],
            'aiSummary' => $aiSummary,
            'economyArticles' => $newsArticles,
            'sidebarArticles' => $latestArticles,
            'seoTitle' => 'Bolsa y mercados — '.$site->name,
            'seoDescription' => 'Cotizaciones del IBEX 35 por empresa, Mercado Continuo y bolsas europeas en '.$site->name,
        ]);
    }
}
