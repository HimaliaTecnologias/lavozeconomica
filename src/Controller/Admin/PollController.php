<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Poll;
use App\Editorial\PollSyncService;
use App\Repository\Site\PollRepository;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/polls')]
#[IsGranted('ROLE_USER')]
final class PollController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly PollSyncService $pollSync,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_polls_index', methods: ['GET'])]
    public function index(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::POLLS);
        $repo = $this->pollRepository($this->siteEmFactory->forContext($site));

        return $this->render('admin/poll/index.html.twig', [
            'site' => $site,
            'polls' => $repo->findAllOrdered(),
            'articleCounts' => $repo->countArticlesByPoll(),
        ]);
    }

    #[Route('/new', name: 'admin_polls_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::POLLS);
        $em = $this->siteEmFactory->forContext($site);

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $em, $site, null);
        }

        return $this->render('admin/poll/form.html.twig', [
            'site' => $site,
            'poll' => null,
            'errors' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_polls_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::POLLS);
        $em = $this->siteEmFactory->forContext($site);
        $poll = $this->findPollOr404($em, $id);

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $em, $site, $poll);
        }

        return $this->render('admin/poll/form.html.twig', [
            'site' => $site,
            'poll' => $poll,
            'errors' => [],
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_polls_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::POLLS);
        $em = $this->siteEmFactory->forContext($site);
        $poll = $this->findPollOr404($em, $id);

        if (!$this->isCsrfTokenValid('delete_poll'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $em->remove($poll);
        $em->flush();

        $this->addFlash('success', 'Encuesta eliminada.');

        return $this->redirectToRoute('admin_polls_index');
    }

    private function handleSave(Request $request, EntityManagerInterface $em, $site, ?Poll $poll): Response
    {
        if (!$this->isCsrfTokenValid('poll_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $question = trim((string) $request->request->get('question', ''));
        $isOpen = $request->request->has('isOpen');
        $showResults = $request->request->has('showResultsBeforeVote');
        $closesAtRaw = trim((string) $request->request->get('closesAt', ''));
        $options = $this->pollSync->labelsFromRequest($request->request->all('options'));

        $errors = [];
        if ($question === '') {
            $errors['question'] = 'La pregunta es obligatoria.';
        }

        $closesAt = null;
        if ($closesAtRaw !== '') {
            try {
                $closesAt = new \DateTimeImmutable($closesAtRaw);
            } catch (\Throwable) {
                $errors['closesAt'] = 'Fecha de cierre no válida.';
            }
        }

        if ($errors === []) {
            try {
                if ($poll === null) {
                    $poll = $this->pollSync->createPoll($question, $options, $isOpen, $showResults, $closesAt, $em);
                } else {
                    $this->pollSync->syncPoll($poll, $question, $options, $isOpen, $showResults, $closesAt, $em);
                }
                $em->flush();

                $this->addFlash('success', 'Encuesta guardada.');

                return $this->redirectToRoute('admin_polls_index');
            } catch (\InvalidArgumentException $e) {
                $errors['options'] = $e->getMessage();
            }
        }

        return $this->render('admin/poll/form.html.twig', [
            'site' => $site,
            'poll' => $poll,
            'errors' => $errors,
            'formData' => $request->request->all(),
        ]);
    }

    private function findPollOr404(EntityManagerInterface $em, int $id): Poll
    {
        $repo = $this->pollRepository($em);
        $poll = $repo->findWithOptions($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Encuesta no encontrada.');
        }

        return $poll;
    }

    private function pollRepository(EntityManagerInterface $em): PollRepository
    {
        $repo = $em->getRepository(Poll::class);
        if (!$repo instanceof PollRepository) {
            throw new \RuntimeException('PollRepository no disponible.');
        }

        return $repo;
    }
}
