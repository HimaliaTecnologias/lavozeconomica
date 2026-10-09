<?php

namespace App\Controller;

use App\Core\Site\ArchiveSidebarProvider;
use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Repository\Site\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PublicAgendaController extends AbstractController
{
    private const PER_PAGE = 20;

    #[Route('/agenda', name: 'public_agenda', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        Request $request,
    ): Response {
        $fecha = (string) $request->query->get('fecha', '');
        if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            return $this->agendaForDate($holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $fecha, $request);
        }

        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid'));

        return $this->agendaForDate($holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $today->format('Y-m-d'), $request);
    }

    #[Route('/agenda/{date}', name: 'public_agenda_date', methods: ['GET'], requirements: ['date' => '\d{4}-\d{2}-\d{2}'])]
    public function date(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        string $date,
        Request $request,
    ): Response {
        return $this->agendaForDate($holder, $pageContextFactory, $siteEmFactory, $sidebarProvider, $date, $request);
    }

    private function agendaForDate(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
        ArchiveSidebarProvider $sidebarProvider,
        string $date,
        Request $request,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $tz = new \DateTimeZone('Europe/Madrid');
        $page = max(1, (int) $request->query->get('page', 1));

        $day = \DateTimeImmutable::createFromFormat('Y-m-d', $date, $tz);
        if ($day === false) {
            throw $this->createNotFoundException('Fecha no válida.');
        }
        $dayStart = new \DateTimeImmutable($day->format('Y-m-d').' 00:00:00', $tz);

        $today = new \DateTimeImmutable('today', $tz);
        $todayStart = new \DateTimeImmutable($today->format('Y-m-d').' 00:00:00', $tz);
        $isToday = $dayStart->format('Y-m-d') === $todayStart->format('Y-m-d');

        $repo = $this->articleRepository($siteEmFactory->forContext($site));
        $result = $repo->findPublishedOnAgendaForDatePaginated($dayStart, $page, self::PER_PAGE);
        $total = $result['total'];
        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));

        if ($total > 0 && $page > $totalPages) {
            $params = ['date' => $dayStart->format('Y-m-d')];
            if ($totalPages > 1) {
                $params['page'] = $totalPages;
            }

            return $this->redirectToRoute(
                $isToday && $totalPages === 1 ? 'public_agenda' : 'public_agenda_date',
                $isToday && $totalPages === 1 ? [] : $params,
                Response::HTTP_MOVED_PERMANENTLY,
            );
        }

        $dateIso = $dayStart->format('Y-m-d');
        $canonical = $isToday && $page === 1
            ? $this->generateUrl('public_agenda', [], UrlGeneratorInterface::ABSOLUTE_URL)
            : $this->generateUrl(
                'public_agenda_date',
                array_filter(['date' => $dateIso, 'page' => $page > 1 ? $page : null]),
                UrlGeneratorInterface::ABSOLUTE_URL,
            );

        $prevDay = $dayStart->modify('-1 day');
        $nextDay = $dayStart->modify('+1 day');
        $canGoNext = $nextDay <= $todayStart;

        $agendaRoute = $isToday ? 'public_agenda' : 'public_agenda_date';
        $agendaParams = $isToday
            ? ['fecha' => $dateIso]
            : ['date' => $dateIso];

        return $this->render('public/agenda/index.html.twig', array_merge([
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'articles' => $result['items'],
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'agendaDate' => $dayStart,
            'agendaDateIso' => $dateIso,
            'isToday' => $isToday,
            'prevDayIso' => $prevDay->format('Y-m-d'),
            'nextDayIso' => $canGoNext ? $nextDay->format('Y-m-d') : null,
            'paginationRoute' => $agendaRoute,
            'paginationParams' => $agendaParams,
            'seoTitle' => ($isToday ? 'Agenda de hoy' : 'Agenda del '.$dayStart->format('d/m/Y')).' — '.$site->name,
            'seoDescription' => 'Conciertos, actividades y eventos vigentes el '.$dayStart->format('d/m/Y').' en '.$site->name,
            'canonicalUrl' => $canonical,
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
