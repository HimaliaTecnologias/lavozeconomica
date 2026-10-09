<?php

namespace App\Controller;

use App\Core\Site\ArchiveSidebarProvider;
use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Editorial\EditionNewspaperPdfGenerator;
use App\Editorial\PublicSearchLogger;
use App\Repository\Site\ArticleRepository;
use App\Security\AbuseRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicArchiveController extends AbstractController
{
    private const SEARCH_PER_PAGE = 40;
    private const EDITION_PER_PAGE = 12;
    private const SEARCH_SESSION_KEY = 'archive_search_q';
    private const SEARCH_QUERY_MAX_LENGTH = 200;

    #[Route('/buscar/pagina/{page}', name: 'public_search_paged', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function searchPaged(
        int $page,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        AbuseRateLimiter $abuseRateLimiter,
        PublicSearchLogger $searchLogger,
        \Symfony\Component\HttpFoundation\Request $request,
    ): Response {
        return $this->renderSearch($page, $holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $abuseRateLimiter, $searchLogger, $request);
    }

    #[Route('/buscar', name: 'public_search', methods: ['GET'])]
    public function search(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        AbuseRateLimiter $abuseRateLimiter,
        PublicSearchLogger $searchLogger,
        \Symfony\Component\HttpFoundation\Request $request,
    ): Response {
        // Do not accept search queries via GET ?q= (bots/scrapers). Only session from POST.
        if ($request->query->has('q')) {
            $page = max(1, (int) $request->query->get('page', 1));

            return $page > 1
                ? $this->redirectToRoute('public_search_paged', ['page' => $page])
                : $this->redirectToRoute('public_search');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        if ($page > 1) {
            return $this->redirectToRoute('public_search_paged', ['page' => $page], Response::HTTP_MOVED_PERMANENTLY);
        }

        return $this->renderSearch(1, $holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $abuseRateLimiter, $searchLogger, $request);
    }

    private function renderSearch(
        int $page,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        AbuseRateLimiter $abuseRateLimiter,
        PublicSearchLogger $searchLogger,
        \Symfony\Component\HttpFoundation\Request $request,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $q = $this->searchQueryFromSession($request);
        $page = max(1, $page);

        if ($q !== '') {
            $abuseRateLimiter->enforceSearch($request);
        }

        $repo = $this->articleRepository($siteEmFactory->forContext($site));
        if ($q !== '') {
            $result = $repo->searchPublished($q, $page, self::SEARCH_PER_PAGE);
            $listMode = 'search';
        } else {
            $result = $repo->findPublishedPaginated($page, self::SEARCH_PER_PAGE);
            $listMode = 'latest';
        }

        $totalArticles = $result['total'];
        $totalPages = max(1, (int) ceil($totalArticles / self::SEARCH_PER_PAGE));

        if ($totalArticles > 0 && $page > $totalPages) {
            return $totalPages > 1
                ? $this->redirectToRoute('public_search_paged', ['page' => $totalPages], Response::HTTP_MOVED_PERMANENTLY)
                : $this->redirectToRoute('public_search', [], Response::HTTP_MOVED_PERMANENTLY);
        }

        $canonical = $page > 1
            ? $this->generateUrl('public_search_paged', ['page' => $page], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->generateUrl('public_search', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $searchLogger->recordIfNeeded($site, $request, $q, $page, $totalArticles);

        return $this->render('public/archive/search.html.twig', array_merge([
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'query' => $q,
            'listMode' => $listMode,
            'articles' => $result['items'],
            'totalArticles' => $totalArticles,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => self::SEARCH_PER_PAGE,
            'seoCanonical' => $canonical,
            'seoTitle' => ($q !== '' ? 'Buscar: '.$q : 'Buscar').($page > 1 ? ' — página '.$page : '').' — '.$site->name,
            'seoDescription' => $q !== ''
                ? sprintf('%d resultados para «%s» en %s', $totalArticles, $q, $site->name)
                : 'Últimas noticias y búsqueda en el archivo de '.$site->name,
            // Búsqueda y listados con query no deben competir con portada/noticias.
            'seoRobots' => ($q !== '' || $page > 1) ? 'noindex, follow' : 'index, follow',
        ], $sidebarProvider->forRepository($repo, $site)));
    }

    #[Route('/buscar', name: 'public_search_submit', methods: ['POST'])]
    public function searchSubmit(
        \Symfony\Component\HttpFoundation\Request $request,
        AbuseRateLimiter $abuseRateLimiter,
        PublicSearchLogger $searchLogger,
    ): Response {
        if (!$this->isCsrfTokenValid('search_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $abuseRateLimiter->enforceSearch($request);

        $q = $this->normalizeSearchQuery((string) $request->request->get('q', ''));
        $session = $request->getSession();

        if ($q === '') {
            $session->remove(self::SEARCH_SESSION_KEY);
        } else {
            $session->set(self::SEARCH_SESSION_KEY, $q);
        }
        $searchLogger->markPending($request);

        return $this->redirectToRoute('public_search');
    }

    private function searchQueryFromSession(\Symfony\Component\HttpFoundation\Request $request): string
    {
        $fromGet = $this->normalizeSearchQuery((string) $request->query->get('q', ''));
        if ($fromGet !== '') {
            return $fromGet;
        }

        $stored = $request->getSession()->get(self::SEARCH_SESSION_KEY, '');

        return $this->normalizeSearchQuery(is_string($stored) ? $stored : '');
    }

    private function normalizeSearchQuery(string $query): string
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query) ?? $query);
        if ($query === '') {
            return '';
        }

        if (strlen($query) > self::SEARCH_QUERY_MAX_LENGTH) {
            $query = substr($query, 0, self::SEARCH_QUERY_MAX_LENGTH);
        }

        return $query;
    }

    #[Route('/edicion', name: 'public_edition', methods: ['GET'])]
    public function editionToday(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        \Symfony\Component\HttpFoundation\Request $request,
    ): Response {
        $fecha = (string) $request->query->get('fecha', '');
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            return $this->editionForDate($holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $fecha, $request);
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid'));

        return $this->editionForDate($holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $today->format('Y-m-d'), $request);
    }

    #[Route('/edicion/{date}', name: 'public_edition_date', methods: ['GET'], requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function editionDate(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        string $date,
        \Symfony\Component\HttpFoundation\Request $request,
    ): Response {
        return $this->editionForDate($holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $date, $request);
    }

    #[Route('/edicion/pdf', name: 'public_edition_pdf', methods: ['GET'], priority: 50)]
    public function editionPdfToday(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        EditionNewspaperPdfGenerator $pdfGenerator,
        \Symfony\Component\HttpFoundation\Request $request,
        AbuseRateLimiter $abuseRateLimiter,
    ): Response {
        $abuseRateLimiter->enforcePdf($request);

        $fecha = (string) $request->query->get('fecha', '');
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            return $this->editionPdfForDate($holder, $pageContextFactory, $siteEmFactory, $pdfGenerator, $fecha);
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid'));

        return $this->editionPdfForDate($holder, $pageContextFactory, $siteEmFactory, $pdfGenerator, $today->format('Y-m-d'));
    }

    #[Route('/edicion/{date}/pdf', name: 'public_edition_pdf_date', methods: ['GET'], requirements: ['date' => '\d{4}-\d{2}-\d{2}'], priority: 50)]
    public function editionPdfDate(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        EditionNewspaperPdfGenerator $pdfGenerator,
        \Symfony\Component\HttpFoundation\Request $request,
        AbuseRateLimiter $abuseRateLimiter,
        string $date,
    ): Response {
        $abuseRateLimiter->enforcePdf($request);

        return $this->editionPdfForDate($holder, $pageContextFactory, $siteEmFactory, $pdfGenerator, $date);
    }

    private function editionPdfForDate(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        EditionNewspaperPdfGenerator $pdfGenerator,
        string $date,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $dayStart = $this->resolveEditionDayStart($date);

        $repo = $this->articleRepository($siteEmFactory->forContext($site));
        $articles = $repo->findPublishedOnDate($dayStart);

        $pdf = $pdfGenerator->generate(
            $site,
            $pageContext['theme'],
            $dayStart,
            $articles,
        );

        $filename = sprintf(
            'edicion-%s-%s.pdf',
            $site->code,
            $dayStart->format('Y-m-d'),
        );

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
            'Content-Length' => (string) strlen($pdf),
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function resolveEditionDayStart(string $date): \DateTimeImmutable
    {
        $tz = new \DateTimeZone('Europe/Madrid');
        $day = \DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);
        if ($day === false) {
            throw $this->createNotFoundException('Fecha no válida.');
        }

        $dayStart = new \DateTimeImmutable($day->format('Y-m-d').' 00:00:00', $tz);
        $today = new \DateTimeImmutable('today', $tz);
        $todayStart = new \DateTimeImmutable($today->format('Y-m-d').' 00:00:00', $tz);
        if ($dayStart > $todayStart) {
            throw $this->createNotFoundException('No hay ediciones futuras.');
        }

        return $dayStart;
    }

    private function editionForDate(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        string $date,
        \Symfony\Component\HttpFoundation\Request $request,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $tz = new \DateTimeZone('Europe/Madrid');
        $page = max(1, (int) $request->query->get('page', 1));

        $dayStart = $this->resolveEditionDayStart($date);

        $today = new \DateTimeImmutable('today', $tz);
        $todayStart = new \DateTimeImmutable($today->format('Y-m-d').' 00:00:00', $tz);

        $repo = $this->articleRepository($siteEmFactory->forContext($site));
        $result = $repo->findPublishedOnDatePaginated($dayStart, $page, self::EDITION_PER_PAGE);
        $totalPages = max(1, (int) ceil($result['total'] / self::EDITION_PER_PAGE));

        $firstPublished = $repo->findFirstPublishedDate();
        $minDate = $firstPublished !== null
            ? $firstPublished->setTimezone($tz)->format('Y-m-d')
            : $todayStart->format('Y-m-d');

        $from = $todayStart->modify('-6 days');
        if ($from < new \DateTimeImmutable($minDate, $tz)) {
            $from = new \DateTimeImmutable($minDate, $tz);
        }
        $activeDates = $repo->findPublishedDatesBetween($from, $todayStart->modify('+1 day'));
        usort($activeDates, static fn (string $a, string $b): int => strcmp($b, $a));
        $activeDates = array_slice($activeDates, 0, 7);

        $editionRoute = $request->attributes->get('_route') === 'public_edition_date'
            ? 'public_edition_date'
            : 'public_edition';
        $editionParams = $editionRoute === 'public_edition_date'
            ? ['date' => $dayStart->format('Y-m-d')]
            : ['fecha' => $dayStart->format('Y-m-d')];

        return $this->render('public/archive/edition.html.twig', array_merge([
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'editionDate' => $dayStart,
            'editionDateIso' => $dayStart->format('Y-m-d'),
            'minDateIso' => $minDate,
            'maxDateIso' => $todayStart->format('Y-m-d'),
            'activeDates' => $activeDates,
            'articles' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'totalPages' => $totalPages,
            'isToday' => $dayStart->format('Y-m-d') === $todayStart->format('Y-m-d'),
            'paginationRoute' => $editionRoute,
            'paginationParams' => $editionParams,
            'editionPdfUrl' => $this->generateUrl('public_edition_pdf_date', ['date' => $dayStart->format('Y-m-d')]),
        ], $sidebarProvider->forRepository($repo, $site)));
    }

    private function articleRepository(\Doctrine\ORM\EntityManagerInterface $em): ArticleRepository
    {
        $repo = $em->getRepository(\App\Entity\Site\Article::class);
        if (!$repo instanceof ArticleRepository) {
            throw new \RuntimeException('ArticleRepository no disponible.');
        }

        return $repo;
    }
}
