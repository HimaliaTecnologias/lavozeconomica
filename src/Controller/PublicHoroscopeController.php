<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteSettingsReader;
use App\Horoscope\HoroscopeAiService;
use App\Horoscope\HoroscopeSignRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicHoroscopeController extends AbstractController
{
    #[Route('/horoscopo', name: 'public_horoscope', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        HoroscopeAiService $horoscopeService,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getHoroscopeConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('El horóscopo no está disponible.');
        }

        $pageContext = $pageContextFactory->build($site);
        $board = $horoscopeService->getBoard($site);

        return $this->render('public/horoscope/index.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'config' => $config,
            'board' => $board,
            'seoTitle' => 'Horóscopo de hoy — '.$site->name,
            'seoDescription' => 'Horóscopo diario para todos los signos en '.$site->name,
        ]);
    }

    #[Route('/horoscopo/{slug}', name: 'public_horoscope_sign', methods: ['GET'])]
    public function sign(
        string $slug,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        HoroscopeAiService $horoscopeService,
    ): Response {
        $meta = HoroscopeSignRegistry::find($slug);
        if ($meta === null) {
            throw $this->createNotFoundException('Signo no encontrado.');
        }

        $site = $holder->require();
        $config = $settingsReader->getHoroscopeConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('El horóscopo no está disponible.');
        }

        $pageContext = $pageContextFactory->build($site);
        $board = $horoscopeService->getBoard($site);
        $sign = $board?->find($slug);

        if ($sign === null) {
            throw $this->createNotFoundException('Horóscopo no disponible para este signo.');
        }

        return $this->render('public/horoscope/show.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'config' => $config,
            'board' => $board,
            'sign' => $sign,
            'seoTitle' => 'Horóscopo '.$sign->name.' — '.$site->name,
            'seoDescription' => 'Horóscopo de hoy para '.$sign->name.' en '.$site->name,
        ]);
    }
}
