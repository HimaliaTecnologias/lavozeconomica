<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Enum\NewsletterSubscriberStatus;
use App\Entity\Site\NewsletterSubscriber;
use App\Repository\Site\NewsletterSubscriberRepository;
use App\Security\SiteAuthorizationChecker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/newsletter')]
#[IsGranted('ROLE_USER')]
final class NewsletterController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_newsletter_index', methods: ['GET'])]
    public function index(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::NEWSLETTER);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $this->subscriberRepository($em);

        return $this->render('admin/newsletter/index.html.twig', [
            'site' => $site,
            'subscribers' => $repo->findAllOrdered(),
            'confirmedCount' => $repo->countByStatus(NewsletterSubscriberStatus::Confirmed),
            'pendingCount' => $repo->countByStatus(NewsletterSubscriberStatus::Pending),
        ]);
    }

    private function subscriberRepository(\Doctrine\ORM\EntityManagerInterface $em): NewsletterSubscriberRepository
    {
        $repo = $em->getRepository(NewsletterSubscriber::class);
        if (!$repo instanceof NewsletterSubscriberRepository) {
            throw new \RuntimeException('NewsletterSubscriberRepository no disponible.');
        }

        return $repo;
    }
}
