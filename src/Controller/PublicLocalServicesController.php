<?php

namespace App\Controller;

use App\Core\Site\ArchiveSidebarProvider;
use App\Core\Site\CategorySectionsProvider;
use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\LocalServices\LocalServicesCatalog;
use App\LocalServices\LocalServicesLocator;
use App\Repository\Site\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicLocalServicesController extends AbstractController
{
    public function __construct(
        private readonly LocalServicesLocator $locator,
        private readonly LocalServicesCatalog $catalog,
    ) {}

    #[Route('/trafico', name: 'public_local_traffic', methods: ['GET', 'POST'])]
    public function traffic(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        return $this->renderService('trafico', $request, $holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $categorySectionsProvider);
    }

    #[Route('/transporte', name: 'public_local_transport', methods: ['GET', 'POST'])]
    public function transport(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        return $this->renderService('transporte', $request, $holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $categorySectionsProvider);
    }

    #[Route('/farmacias', name: 'public_local_pharmacies', methods: ['GET', 'POST'])]
    public function pharmacies(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        return $this->renderService('farmacias', $request, $holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $categorySectionsProvider);
    }

    #[Route('/telefonos-utiles', name: 'public_local_phones', methods: ['GET', 'POST'])]
    public function phones(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        return $this->renderService('telefonos-utiles', $request, $holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $categorySectionsProvider);
    }

    private function renderService(
        string $type,
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        CategorySectionsProvider $categorySectionsProvider,
    ): Response {
        $meta = LocalServicesCatalog::TYPES[$type] ?? null;
        if ($meta === null) {
            throw $this->createNotFoundException();
        }

        $site = $holder->require();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('local_services_cp', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }
            $posted = preg_replace('/\D/', '', (string) $request->request->get('postalCode', '')) ?? '';
            $this->locator->rememberPostal($posted);
            $resolved = $this->locator->resolve($site, $posted);

            return $this->redirectToRoute(
                $meta['route'],
                $resolved !== null ? ['cp' => $resolved->postalCode] : ['cp' => $posted],
            );
        }

        $error = null;
        $postalInput = $this->locator->defaultPostalHint($site);
        $queryCp = preg_replace('/\D/', '', (string) $request->query->get('cp', '')) ?? '';
        if ($queryCp !== '') {
            $postalInput = $queryCp;
        } else {
            $sessionCp = (string) $request->getSession()->get(LocalServicesLocator::SESSION_KEY, '');
            if ($sessionCp !== '') {
                $postalInput = $sessionCp;
            }
        }

        $location = $this->locator->resolve($site, $postalInput !== '' ? $postalInput : null);
        if ($location !== null) {
            $postalInput = $location->postalCode;
        } elseif ($postalInput !== '') {
            $error = 'No se encontró la localidad para ese código postal.';
        }

        $payload = $location !== null
            ? $this->catalog->build($type, $location)
            : ['links' => [], 'phones' => [], 'mapEmbedUrl' => null];

        $pageContext = $pageContextFactory->build($site);
        $contentModules = $pageContext['contentModulesConfig'];
        $repo = $siteEmFactory->forContext($site)->getRepository(\App\Entity\Site\Article::class);
        $sidebar = $repo instanceof ArticleRepository
            ? $sidebarProvider->forRepository($repo, $site)
            : [
                'latestArticles' => [],
                'popularArticles' => [],
                'topRatedArticles' => [],
                'popularPeriod' => 'week',
            ];

        $relatedArticles = $sidebar['latestArticles'];
        $categorySections = [];
        $loadCategorySections = false;
        $relatedLimit = 4;
        foreach ($contentModules->enabledFor('local_services') as $module) {
            if ($module->type->value === 'related' && $module->count > 0) {
                $relatedLimit = $module->count;
            }
            if ($module->type->value === 'category_sections') {
                $loadCategorySections = true;
            }
        }
        if ($repo instanceof ArticleRepository && $relatedLimit > count($relatedArticles)) {
            $relatedArticles = $repo->findPublishedLatest($relatedLimit);
        } else {
            $relatedArticles = \array_slice($relatedArticles, 0, $relatedLimit);
        }
        if ($loadCategorySections) {
            $sectionCount = max(2, min(4, (int) ($pageContext['layout']['homeCategoryArticlesCount'] ?? 4)));
            $categorySections = $categorySectionsProvider->forNavCategories(
                $site,
                $pageContext['navCategories'],
                $sectionCount,
            );
        }

        return $this->render('public/local_services/show.html.twig', array_merge([
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'contentModulesConfig' => $contentModules,
            'serviceType' => $type,
            'serviceTitle' => $meta['title'],
            'serviceIntro' => $meta['intro'],
            'serviceRoute' => $meta['route'],
            'location' => $location,
            'postalInput' => $postalInput,
            'postalError' => $error,
            'links' => $payload['links'],
            'phones' => $payload['phones'],
            'mapEmbedUrl' => $payload['mapEmbedUrl'],
            'siblingServices' => LocalServicesCatalog::TYPES,
            'relatedArticles' => $relatedArticles,
            'categorySections' => $categorySections,
            'seoTitle' => $meta['title'].($location ? ' en '.$location->placeName : '').' — '.$site->name,
            'seoDescription' => $meta['intro'].' Consulta por código postal en '.$site->name.'.',
            'canonicalUrl' => $this->generateUrl(
                $meta['route'],
                $location !== null ? ['cp' => $location->postalCode] : [],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ], $sidebar));
    }
}
