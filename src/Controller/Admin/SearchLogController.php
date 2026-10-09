<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\SearchQueryLog;
use App\Repository\Site\SearchQueryLogRepository;
use App\Security\SiteAuthorizationChecker;
use App\Security\SitePermission;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/busquedas')]
#[IsGranted('ROLE_USER')]
final class SearchLogController extends AbstractAdminController
{
    private const PER_PAGE = 40;

    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_search_logs', methods: ['GET'])]
    #[Route('/pagina/{page}', name: 'admin_search_logs_paged', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function index(Request $request, int $page = 1): Response
    {
        [$site] = $this->requirePermission(SitePermission::DASHBOARD);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $em->getRepository(SearchQueryLog::class);

        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) > 200) {
            $query = mb_substr($query, 0, 200);
        }
        $page = max(1, $page);

        $tz = new \DateTimeZone('Europe/Madrid');
        $today = new \DateTimeImmutable('today', $tz);
        $week = $today->modify('-7 days');

        $stats = [
            'today' => 0,
            'week' => 0,
            'distinctWeek' => 0,
        ];
        $topQueries = [];
        $items = [];
        $total = 0;
        $schemaReady = $repo instanceof SearchQueryLogRepository;

        if ($schemaReady) {
            try {
                $stats['today'] = $repo->countSince($today);
                $stats['week'] = $repo->countSince($week);
                $stats['distinctWeek'] = $repo->countDistinctQueriesSince($week);
                $topQueries = $repo->topQueriesSince($week, 15);
                $result = $repo->searchPaginated($query, $page, self::PER_PAGE);
                $items = $result['items'];
                $total = $result['total'];
            } catch (\Throwable) {
                $schemaReady = false;
            }
        }

        $totalPages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($total > 0 && $page > $totalPages) {
            return $this->redirectToRoute('admin_search_logs_paged', array_filter([
                'page' => $totalPages,
                'q' => $query !== '' ? $query : null,
            ]));
        }

        return $this->render('admin/search_log/index.html.twig', [
            'site' => $site,
            'schemaReady' => $schemaReady,
            'stats' => $stats,
            'topQueries' => $topQueries,
            'logs' => $items,
            'query' => $query,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'perPage' => self::PER_PAGE,
        ]);
    }
}
