<?php

namespace App\Controller;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Entity\Site\Enum\ArticleStatus;
use App\Security\AbuseRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiArticleController extends AbstractController
{
    #[Route('/api/v1/articles', name: 'api_articles_list', methods: ['GET'])]
    public function list(
        Request $request,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $factory,
        AbuseRateLimiter $abuseRateLimiter,
    ): JsonResponse {
        $abuseRateLimiter->enforceApiRead($request);

        $context = $holder->require();
        $em = $factory->forContext($context);

        $articles = $em->getRepository(Article::class)->findBy(
            ['status' => ArticleStatus::Published],
            ['publishedAt' => 'DESC'],
            20,
        );

        $data = array_map(static fn (Article $a) => [
            'id' => $a->getId(),
            'title' => $a->getTitle(),
            'slug' => $a->getSlug(),
            'published_at' => $a->getPublishedAt()?->format(\DateTimeInterface::ATOM),
        ], $articles);

        return new JsonResponse([
            'site' => $context->code,
            'data' => $data,
        ]);
    }
}
