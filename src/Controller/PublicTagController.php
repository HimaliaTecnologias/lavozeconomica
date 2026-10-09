<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Repository\Site\ArticleRepository;
use App\Repository\Site\ArticleTagRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicTagController extends AbstractController
{
    private const PER_PAGE = 25;

    #[Route(
        '/etiqueta/{slug}/pagina/{page}',
        name: 'public_tag_show_paged',
        requirements: ['slug' => '[a-z0-9-]+', 'page' => '\d+'],
        methods: ['GET'],
    )]
    public function showPaged(
        string $slug,
        int $page,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
    ): Response {
        return $this->renderTag($slug, $page, $holder, $pageContextFactory, $siteEmFactory);
    }

    #[Route(
        '/etiqueta/{slug}',
        name: 'public_tag_show',
        requirements: ['slug' => '[a-z0-9-]+'],
        methods: ['GET'],
    )]
    public function show(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
    ): Response {
        $page = max(1, (int) $request->query->get('page', 1));

        if ($page > 1) {
            return $this->redirectToRoute('public_tag_show_paged', [
                'slug' => strtolower($slug),
                'page' => $page,
            ], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->renderTag($slug, 1, $holder, $pageContextFactory, $siteEmFactory);
    }

    private function renderTag(
        string $slug,
        int $page,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $em = $siteEmFactory->forContext($site);
        $tagRepo = $this->tagRepository($em);

        $normalizedSlug = strtolower($slug);
        if ($slug !== $normalizedSlug) {
            return $page > 1
                ? $this->redirectToRoute('public_tag_show_paged', ['slug' => $normalizedSlug, 'page' => $page], Response::HTTP_MOVED_PERMANENTLY)
                : $this->redirectToRoute('public_tag_show', ['slug' => $normalizedSlug], Response::HTTP_MOVED_PERMANENTLY);
        }

        $tag = $tagRepo->findOneBySlug($normalizedSlug);
        if ($tag === null) {
            throw $this->createNotFoundException('Etiqueta no encontrada.');
        }

        if ($tag->getSlug() !== $normalizedSlug) {
            return $page > 1
                ? $this->redirectToRoute('public_tag_show_paged', ['slug' => $tag->getSlug(), 'page' => $page], Response::HTTP_MOVED_PERMANENTLY)
                : $this->redirectToRoute('public_tag_show', ['slug' => $tag->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $repo = $this->articleRepository($em);
        $page = max(1, $page);
        $result = $repo->findPublishedByTagSlugPaginated($tag->getSlug(), $page, self::PER_PAGE);
        $totalArticles = $result['total'];
        $totalPages = max(1, (int) ceil($totalArticles / self::PER_PAGE));

        if ($page > $totalPages && $totalArticles > 0) {
            return $page > 1 && $totalPages > 1
                ? $this->redirectToRoute('public_tag_show_paged', ['slug' => $tag->getSlug(), 'page' => $totalPages], Response::HTTP_MOVED_PERMANENTLY)
                : $this->redirectToRoute('public_tag_show', ['slug' => $tag->getSlug()], Response::HTTP_MOVED_PERMANENTLY);
        }

        $articles = $result['items'];
        $latestOverall = $repo->findPublishedLatest(6);

        $canonical = $page > 1
            ? $this->generateUrl('public_tag_show_paged', ['slug' => $tag->getSlug(), 'page' => $page], UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->generateUrl('public_tag_show', ['slug' => $tag->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL);

        $description = 'Noticias sobre '.$tag->getName().' en '.$site->name;

        return $this->render('public/tag/show.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'tag' => $tag,
            'articles' => $articles,
            'latestOverall' => $latestOverall,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalArticles' => $totalArticles,
            'perPage' => self::PER_PAGE,
            'seoCanonical' => $canonical,
            'seoDescription' => $description,
            'seoTitle' => $tag->getName().' — '.$site->name,
            'seoRobots' => $page > 1 ? 'noindex, follow' : null,
        ]);
    }

    private function articleRepository(\Doctrine\ORM\EntityManagerInterface $em): ArticleRepository
    {
        $repo = $em->getRepository(\App\Entity\Site\Article::class);
        if (!$repo instanceof ArticleRepository) {
            throw new \RuntimeException('ArticleRepository no disponible.');
        }

        return $repo;
    }

    private function tagRepository(\Doctrine\ORM\EntityManagerInterface $em): ArticleTagRepository
    {
        $repo = $em->getRepository(\App\Entity\Site\ArticleTag::class);
        if (!$repo instanceof ArticleTagRepository) {
            throw new \RuntimeException('ArticleTagRepository no disponible.');
        }

        return $repo;
    }
}
