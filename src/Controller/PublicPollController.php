<?php

namespace App\Controller;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Poll;
use App\Editorial\PollVoteService;
use App\Repository\Site\PollRepository;
use App\Security\AbuseRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPollController extends AbstractController
{
    #[Route('/encuesta/{id}/votar', name: 'public_poll_vote', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function vote(
        int $id,
        Request $request,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        PollVoteService $voteService,
        AbuseRateLimiter $abuseRateLimiter,
    ): Response {
        $site = $holder->require();
        $em = $siteEmFactory->forContext($site);
        $repo = $em->getRepository(Poll::class);
        if (!$repo instanceof PollRepository) {
            throw $this->createNotFoundException();
        }

        $poll = $repo->findWithOptions($id);
        if ($poll === null) {
            throw $this->createNotFoundException('Encuesta no encontrada.');
        }

        if (!$this->isCsrfTokenValid('poll_vote'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $abuseRateLimiter->enforcePublicForm($request, 'poll');

        $optionId = (int) $request->request->get('optionId', 0);
        $returnUrl = (string) $request->request->get('returnUrl', '');
        $voterKey = $voteService->resolveVoterKey($request);

        try {
            $voteService->castVote($poll, $optionId, $voterKey, $em);
            $this->addFlash('success', 'Gracias por votar.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        $response = $returnUrl !== '' && str_starts_with($returnUrl, '/')
            ? $this->redirect($returnUrl)
            : $this->redirectToRoute('home');

        $voteService->attachVoterCookie($response, $voterKey);

        return $response;
    }
}
