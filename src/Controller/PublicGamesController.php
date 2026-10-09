<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Games\GamesService;
use App\Repository\Site\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicGamesController extends AbstractController
{
    public function __construct(
        private readonly SiteContextHolder $holder,
        private readonly PublicPageContextFactory $pageContextFactory,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly GamesService $gamesService,
    ) {}

    #[Route('/pasatiempos', name: 'public_games', methods: ['GET'])]
    public function index(): Response
    {
        $site = $this->holder->require();
        $config = $this->gamesService->getConfig($site);

        if (!$config->enabled || !$config->isConfigured()) {
            throw $this->createNotFoundException('La sección de pasatiempos no está disponible.');
        }

        // Asegura retos del día al entrar en la sección.
        if ($config->sudokuEnabled) {
            $this->gamesService->getDailySudoku($site);
        }
        if ($config->wordScrambleEnabled) {
            $this->gamesService->getDailyWordScramble($site);
        }

        return $this->render('public/games/index.html.twig', $this->pageVars($site, $config, [
            'seoTitle' => 'Pasatiempos — '.$site->name,
            'seoDescription' => 'Sudoku, anagramas y juegos del día en '.$site->name,
        ]));
    }

    #[Route('/pasatiempos/sudoku', name: 'public_games_sudoku', methods: ['GET'])]
    public function sudoku(): Response
    {
        $site = $this->holder->require();
        $config = $this->gamesService->getConfig($site);

        if (!$config->enabled || !$config->sudokuEnabled) {
            throw $this->createNotFoundException('El sudoku no está disponible.');
        }

        $puzzle = $this->gamesService->getDailySudoku($site);

        return $this->render('public/games/sudoku.html.twig', $this->pageVars($site, $config, [
            'puzzle' => $puzzle,
            'seoTitle' => 'Sudoku del día — '.$site->name,
            'seoDescription' => 'Sudoku diario en '.$site->name,
        ]));
    }

    #[Route('/pasatiempos/anagrama', name: 'public_games_anagrama', methods: ['GET'])]
    public function anagrama(): Response
    {
        $site = $this->holder->require();
        $config = $this->gamesService->getConfig($site);

        if (!$config->enabled || !$config->wordScrambleEnabled) {
            throw $this->createNotFoundException('El anagrama no está disponible.');
        }

        $puzzle = $this->gamesService->getDailyWordScramble($site);

        return $this->render('public/games/anagrama.html.twig', $this->pageVars($site, $config, [
            'puzzle' => $puzzle,
            'seoTitle' => 'Anagrama del día — '.$site->name,
            'seoDescription' => 'Anagrama diario en '.$site->name,
        ]));
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function pageVars(\App\Core\Site\SiteContext $site, \App\Core\Site\Dto\GamesConfig $config, array $extra = []): array
    {
        $pageContext = $this->pageContextFactory->build($site);
        $em = $this->siteEmFactory->forContext($site);
        $articleRepo = $em->getRepository(Article::class);
        $latestLimit = max(3, min(12, (int) ($pageContext['layout']['sidebarLatestCount'] ?? 5)));
        $latestArticles = $articleRepo instanceof ArticleRepository
            ? $articleRepo->findPublishedLatest($latestLimit)
            : [];

        return array_merge([
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'config' => $config,
            'latestArticles' => $latestArticles,
        ], $extra);
    }
}
