<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;
use App\Repository\Site\WeatherWebcamRepository;
use App\Weather\WeatherCodeMapper;
use App\Weather\WeatherAiSummaryService;
use App\Weather\WeatherService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicWeatherController extends AbstractController
{
    #[Route('/tiempo', name: 'public_weather', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        WeatherService $weatherService,
        WeatherAiSummaryService $weatherAiSummaryService,
    ): Response {
        $site = $holder->require();
        $config = $weatherService->getConfig($site);

        if (!$config->enabled || !$config->isConfigured()) {
            throw $this->createNotFoundException('La sección del tiempo no está disponible.');
        }

        $pageContext = $pageContextFactory->build($site);
        $forecast = $weatherService->getForecast($site);
        $aiSummary = $forecast !== null
            ? $weatherAiSummaryService->getSummary($site, $forecast)
            : null;

        $em = $siteEmFactory->forContext($site);
        $webcamRepo = $em->getRepository(\App\Entity\Site\WeatherWebcam::class);
        $webcams = $webcamRepo instanceof WeatherWebcamRepository
            ? $webcamRepo->findEnabledOrdered()
            : [];

        $articleRepo = $em->getRepository(Article::class);
        $latestLimit = max(3, min(12, (int) ($pageContext['layout']['sidebarLatestCount'] ?? 5)));
        $latestArticles = $articleRepo instanceof ArticleRepository
            ? $articleRepo->findPublishedLatest($latestLimit)
            : [];

        return $this->render('public/weather/index.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'config' => $config,
            'forecast' => $forecast,
            'aiSummary' => $aiSummary,
            'aiSummaryEnabled' => $config->aiSummaryEnabled,
            'webcams' => $webcams,
            'latestArticles' => $latestArticles,
            'weatherCodeMapper' => WeatherCodeMapper::class,
        ]);
    }
}
