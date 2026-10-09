<?php

namespace App\Core\Site;

use App\Core\Site\Dto\Error404Config;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class Error404PageRenderer
{
    public function __construct(
        private readonly SiteContextHolder $siteHolder,
        private readonly SiteSettingsReader $settingsReader,
        private readonly PublicPageContextFactory $pageContextFactory,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly Environment $twig,
    ) {}

    public function render(?string $requestedPath = null, bool $preview = false): ?Response
    {
        $site = $this->siteHolder->get();
        if ($site === null) {
            return null;
        }

        try {
            $config = $this->settingsReader->getError404Config($site);
            $pageContext = $this->pageContextFactory->build($site);
            $latestArticles = [];

            if ($config->showLatestArticles) {
                try {
                    $em = $this->siteEmFactory->forContext($site);
                    $repo = $em->getRepository(Article::class);
                    if ($repo instanceof ArticleRepository) {
                        $latestArticles = $repo->findPublishedLatest(6);
                    }
                } catch (\Throwable) {
                    $latestArticles = [];
                }
            }

            $html = $this->twig->render('public/error/404.html.twig', [
                'site' => $site,
                'theme' => $pageContext['theme'],
                'appearance' => $pageContext['appearance'],
                'navCategories' => $pageContext['navCategories'],
                'layout' => $pageContext['layout'],
                'footer' => $pageContext['footer'],
                'error404' => $config->toArray(),
                'latestArticles' => $latestArticles,
                'requestedPath' => $requestedPath,
                'previewMode' => $preview,
            ]);

            return new Response($html, $preview ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
        } catch (\Throwable) {
            return null;
        }
    }
}
