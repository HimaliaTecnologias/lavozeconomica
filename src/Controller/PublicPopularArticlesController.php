<?php

namespace App\Controller;

use App\Core\Site\PopularArticlesProvider;
use App\Core\Site\PopularPeriod;
use App\Core\Site\SiteContextHolder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPopularArticlesController extends AbstractController
{
    #[Route('/fragmento/mas-vistas', name: 'public_popular_articles', methods: ['GET'])]
    public function list(
        Request $request,
        SiteContextHolder $holder,
        PopularArticlesProvider $popularArticlesProvider,
    ): Response {
        $site = $holder->get();
        if ($site === null) {
            return new JsonResponse(['html' => ''], Response::HTTP_NOT_FOUND);
        }

        $period = PopularPeriod::tryFromRequest($request->query->getString('period'));
        $limit = max(1, min(20, $request->query->getInt('limit', 5)));
        $excludeId = $request->query->getInt('exclude', 0) ?: null;
        $homeOnly = $request->query->getBoolean('home');
        $categoryIds = $this->parseCategoryIds($request->query->getString('categories'));

        $articles = $popularArticlesProvider->forSite(
            $site,
            $period,
            $limit,
            $excludeId,
            $homeOnly,
            $categoryIds,
        );

        $html = $this->renderView('public/_partials/popular_sidebar_list.html.twig', [
            'articles' => $articles,
        ]);

        return new JsonResponse([
            'html' => $html,
            'period' => $period->value,
        ]);
    }

    /**
     * @return list<int>
     */
    private function parseCategoryIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $id = (int) trim($part);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
