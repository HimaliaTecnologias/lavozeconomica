<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Entity\Site\Category;
use App\Entity\Site\Enum\NewsletterFrequency;
use App\Editorial\NewsletterSubscriptionService;
use App\Repository\Site\ArticleRepository;
use App\Repository\Site\CategoryRepository;
use App\Security\AbuseRateLimiter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicNewsletterController extends AbstractController
{
    #[Route('/boletin', name: 'public_newsletter_index', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteEntityManagerFactory $siteEmFactory,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $em = $siteEmFactory->forContext($site);

        $articleRepo = $em->getRepository(Article::class);
        $sidebarArticles = $articleRepo instanceof ArticleRepository
            ? $articleRepo->findPublishedLatest(8)
            : [];

        return $this->render('public/newsletter/index.html.twig', array_merge($pageContext, [
            'site' => $site,
            'categories' => $this->loadCategories($em),
            'sidebarArticles' => $sidebarArticles,
        ]));
    }

    #[Route('/boletin/suscribir', name: 'public_newsletter_subscribe', methods: ['POST'])]
    public function subscribe(
        Request $request,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        NewsletterSubscriptionService $subscriptionService,
        AbuseRateLimiter $abuseRateLimiter,
    ): Response {
        $site = $holder->require();
        $em = $siteEmFactory->forContext($site);

        if (!$this->isCsrfTokenValid('newsletter_subscribe', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        if ($request->request->get('website') !== '') {
            return $this->redirectToRoute('public_newsletter_index');
        }

        $abuseRateLimiter->enforcePublicForm($request, 'newsletter');

        $email = trim((string) $request->request->get('email', ''));
        $frequency = NewsletterFrequency::tryFrom((string) $request->request->get('frequency', ''))
            ?? NewsletterFrequency::Weekly;
        $categoryIds = $request->request->all('categoryIds');

        try {
            $subscriptionService->subscribe($email, $frequency, $categoryIds, $em, $site);
            $this->addFlash('success', 'Revisa tu correo para confirmar la suscripción al boletín.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        } catch (\Throwable) {
            $this->addFlash('error', 'No se pudo procesar la suscripción. Inténtalo más tarde.');
        }

        return $this->redirectToRoute('public_newsletter_index');
    }

    #[Route('/boletin/confirmar/{token}', name: 'public_newsletter_confirm', methods: ['GET'])]
    public function confirm(
        string $token,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        PublicPageContextFactory $pageContextFactory,
        NewsletterSubscriptionService $subscriptionService,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $em = $siteEmFactory->forContext($site);

        try {
            $subscriptionService->confirm($token, $em);
            $this->addFlash('success', 'Suscripción confirmada. Pronto recibirás nuestro boletín.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->render('public/newsletter/confirmed.html.twig', array_merge($pageContext, [
            'site' => $site,
        ]));
    }

    #[Route('/boletin/baja/{token}', name: 'public_newsletter_unsubscribe', methods: ['GET'])]
    public function unsubscribe(
        string $token,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        PublicPageContextFactory $pageContextFactory,
        NewsletterSubscriptionService $subscriptionService,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $em = $siteEmFactory->forContext($site);

        try {
            $subscriptionService->unsubscribe($token, $em);
            $this->addFlash('success', 'Has cancelado la suscripción al boletín.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->render('public/newsletter/unsubscribed.html.twig', array_merge($pageContext, [
            'site' => $site,
        ]));
    }

    #[Route('/boletin/preferencias/{token}', name: 'public_newsletter_preferences', methods: ['GET', 'POST'])]
    public function preferences(
        string $token,
        Request $request,
        SiteContextHolder $holder,
        SiteEntityManagerFactory $siteEmFactory,
        PublicPageContextFactory $pageContextFactory,
        NewsletterSubscriptionService $subscriptionService,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $em = $siteEmFactory->forContext($site);
        $repo = $em->getRepository(\App\Entity\Site\NewsletterSubscriber::class);
        if (!$repo instanceof \App\Repository\Site\NewsletterSubscriberRepository) {
            throw $this->createNotFoundException();
        }
        $subscriber = $repo->findByUnsubscribeToken($token);
        if ($subscriber === null) {
            throw $this->createNotFoundException('Suscripción no encontrada.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('newsletter_preferences', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $frequency = NewsletterFrequency::tryFrom((string) $request->request->get('frequency', ''))
                ?? $subscriber->getFrequency();
            $categoryIds = $request->request->all('categoryIds');

            try {
                $subscriptionService->updatePreferences($token, $frequency, $categoryIds, $em);
                $this->addFlash('success', 'Preferencias actualizadas.');
                $subscriber = $repo->findByUnsubscribeToken($token);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('public/newsletter/preferences.html.twig', array_merge($pageContext, [
            'site' => $site,
            'subscriber' => $subscriber,
            'categories' => $this->loadCategories($em),
        ]));
    }

    /** @return list<Category> */
    private function loadCategories(\Doctrine\ORM\EntityManagerInterface $em): array
    {
        $repo = $em->getRepository(Category::class);
        if ($repo instanceof CategoryRepository) {
            return $repo->findRootsForAdmin();
        }

        return $repo->findBy([], ['name' => 'ASC']);
    }
}
