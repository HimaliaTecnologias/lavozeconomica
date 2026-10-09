<?php

namespace App\Controller\Admin;

use App\Admin\AdminHealthChecker;
use App\Admin\AdminNavBadges;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Entity\Site\Enum\ArticleStatus;
use App\Entity\Site\NewsletterSubscriber;
use App\Repository\Site\AdminActivityLogRepository;
use App\Repository\Site\ArticleRepository;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor')]
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly AdminNavBadges $navBadges,
        private readonly AdminHealthChecker $healthChecker,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        [$site, $user] = $this->requirePermission(\App\Security\SitePermission::DASHBOARD);

        $em = $this->siteEmFactory->forContext($site);
        $articleRepo = $em->getRepository(Article::class);
        $activityRepo = $em->getRepository(\App\Entity\Site\AdminActivityLog::class);

        $stats = [
            'articles_total' => $articleRepo->count([]),
            'articles_published' => $articleRepo->count(['status' => ArticleStatus::Published]),
            'articles_draft' => $articleRepo->count(['status' => ArticleStatus::Draft]),
            'categories' => $em->getRepository(\App\Entity\Site\Category::class)->count([]),
            'media' => $em->getRepository(\App\Entity\Site\Media::class)->count([]),
            'newsletter' => $em->getRepository(NewsletterSubscriber::class)->count([]),
        ];

        $recentArticles = [];
        $draftArticles = [];
        $chartData = [];
        $chartMax = 1;
        if ($articleRepo instanceof ArticleRepository) {
            $recentArticles = $articleRepo->findRecentUpdated(5);
            $draftArticles = $articleRepo->findDrafts(5);
            $chartData = $articleRepo->countPublishedPerDay(14);
            foreach ($chartData as $row) {
                if ($row['count'] > $chartMax) {
                    $chartMax = $row['count'];
                }
            }
        }

        $chartTotal = 0;
        foreach ($chartData as $row) {
            $chartTotal += $row['count'];
        }

        $activity = $activityRepo instanceof AdminActivityLogRepository
            ? $activityRepo->findRecent(10)
            : [];

        $searchTop = [];
        $searchWeekCount = 0;
        $searchRepo = $em->getRepository(\App\Entity\Site\SearchQueryLog::class);
        if ($searchRepo instanceof \App\Repository\Site\SearchQueryLogRepository) {
            try {
                $week = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Madrid')))->modify('-7 days');
                $searchWeekCount = $searchRepo->countSince($week);
                $searchTop = $searchRepo->topQueriesSince($week, 5);
            } catch (\Throwable) {
                $searchTop = [];
            }
        }

        $badges = $this->navBadges->forSite($site);
        $health = $this->healthChecker->check($site);
        $ingestPending = $badges['ingest_pending'];

        return $this->render('admin/dashboard.html.twig', [
            'site' => $site,
            'user' => $user,
            'stats' => $stats,
            'recentArticles' => $recentArticles,
            'draftArticles' => $draftArticles,
            'chartData' => $chartData,
            'chartMax' => $chartMax,
            'chartTotal' => $chartTotal,
            'activity' => $activity,
            'searchTop' => $searchTop,
            'searchWeekCount' => $searchWeekCount,
            'health' => $health,
            'ingestPending' => $ingestPending,
        ]);
    }
}
