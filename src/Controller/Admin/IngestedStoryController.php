<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Entity\Platform\IngestFetchRun;
use App\Entity\Platform\IngestedStory;
use App\Entity\Platform\Site;
use App\Entity\Platform\SitePublication;
use App\Ingestion\HimtecMedia\IngestedStoryPublisher;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/ingest')]
#[IsGranted('ROLE_USER')]
final class IngestedStoryController extends AbstractAdminController
{
    /** @var array<string, string> */
    public const TRIGGER_LABELS = [
        'manual' => 'Manual (panel)',
        'scheduled' => 'Automática',
        'command' => 'Consola',
    ];

    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly IngestedStoryPublisher $publisher,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_ingest_index', methods: ['GET'])]
    public function index(): Response
    {
        [$siteContext] = $this->requirePermission(\App\Security\SitePermission::INGEST);
        $platformSite = $this->platformEm->find(Site::class, $siteContext->id);

        $stories = $this->platformEm->getRepository(IngestedStory::class)->findBy(
            [],
            ['receivedAt' => 'DESC'],
            100,
        );

        $publicationMap = [];
        if ($platformSite !== null) {
            $publications = $this->platformEm->getRepository(SitePublication::class)->findBy(['site' => $platformSite]);
            foreach ($publications as $publication) {
                $storyId = $publication->getIngestedStory()->getId();
                if ($storyId !== null) {
                    $publicationMap[$storyId] = $publication;
                }
            }
        }

        return $this->render('admin/ingest/index.html.twig', [
            'site' => $siteContext,
            'stories' => $stories,
            'publicationMap' => $publicationMap,
            'recentRuns' => $this->loadRecentRuns($platformSite),
            'triggerLabels' => self::TRIGGER_LABELS,
        ]);
    }

    #[Route('/history', name: 'admin_ingest_history', methods: ['GET'])]
    public function history(): Response
    {
        [$siteContext] = $this->requirePermission(\App\Security\SitePermission::INGEST);
        $platformSite = $this->requirePlatformSite($siteContext->id);

        $runs = $this->platformEm->getRepository(IngestFetchRun::class)->findBy(
            ['site' => $platformSite],
            ['ranAt' => 'DESC'],
            100,
        );

        return $this->render('admin/ingest/history.html.twig', [
            'site' => $siteContext,
            'runs' => $runs,
            'triggerLabels' => self::TRIGGER_LABELS,
        ]);
    }

    #[Route('/history/{id}', name: 'admin_ingest_run_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function runShow(int $id): Response
    {
        [$siteContext] = $this->requirePermission(\App\Security\SitePermission::INGEST);
        $run = $this->loadRunForSite($id, $siteContext->id);

        return $this->render('admin/ingest/run_show.html.twig', [
            'site' => $siteContext,
            'run' => $run,
            'triggerLabels' => self::TRIGGER_LABELS,
        ]);
    }

    #[Route('/stories/{id}', name: 'admin_ingest_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        [$siteContext] = $this->requirePermission(\App\Security\SitePermission::INGEST);
        $story = $this->platformEm->find(IngestedStory::class, $id);
        if ($story === null) {
            throw $this->createNotFoundException('Historia no encontrada.');
        }

        $platformSite = $this->platformEm->find(Site::class, $siteContext->id);
        $publication = $platformSite !== null
            ? $this->platformEm->getRepository(SitePublication::class)->findOneBy([
                'site' => $platformSite,
                'ingestedStory' => $story,
            ])
            : null;

        return $this->render('admin/ingest/show.html.twig', [
            'site' => $siteContext,
            'story' => $story,
            'publication' => $publication,
        ]);
    }

    #[Route('/stories/{id}/publish', name: 'admin_ingest_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(Request $request, int $id): Response
    {
        [$siteContext] = $this->requirePermission(\App\Security\SitePermission::INGEST);

        if (!$this->isCsrfTokenValid('ingest_publish'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $story = $this->platformEm->find(IngestedStory::class, $id);
        if ($story === null) {
            throw $this->createNotFoundException('Historia no encontrada.');
        }

        try {
            $article = $this->publisher->publishForSite($siteContext, $story);
            $this->addFlash('success', $article !== null
                ? 'Noticia publicada como artículo en este periódico.'
                : 'La noticia ya estaba publicada en este periódico.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_ingest_show', ['id' => $id]);
    }

    /** @return list<IngestFetchRun> */
    private function loadRecentRuns(?Site $platformSite): array
    {
        if ($platformSite === null) {
            return [];
        }

        return $this->platformEm->getRepository(IngestFetchRun::class)->findBy(
            ['site' => $platformSite],
            ['ranAt' => 'DESC'],
            10,
        );
    }

    private function requirePlatformSite(int $siteId): Site
    {
        $site = $this->platformEm->find(Site::class, $siteId);
        if ($site === null) {
            throw $this->createNotFoundException('Periódico no encontrado.');
        }

        return $site;
    }

    private function loadRunForSite(int $id, int $siteId): IngestFetchRun
    {
        $run = $this->platformEm->find(IngestFetchRun::class, $id);
        if ($run === null || $run->getSite()->getId() !== $siteId) {
            throw $this->createNotFoundException('Ejecución de ingesta no encontrada.');
        }

        return $run;
    }
}
