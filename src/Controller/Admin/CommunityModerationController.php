<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\ClassifiedAd;
use App\Entity\Site\ForumPost;
use App\Entity\Site\ForumTopic;
use App\Repository\Site\ClassifiedAdRepository;
use App\Repository\Site\ForumPostRepository;
use App\Repository\Site\ForumTopicRepository;
use App\Security\SiteAuthorizationChecker;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/comunidad')]
#[IsGranted('ROLE_USER')]
final class CommunityModerationController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('/avisos', name: 'admin_community_classifieds', methods: ['GET'])]
    public function classifieds(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $em->getRepository(ClassifiedAd::class);
        $ads = $repo instanceof ClassifiedAdRepository ? $repo->findRecentForAdmin(100) : [];

        return $this->render('admin/community/classifieds.html.twig', [
            'site' => $site,
            'ads' => $ads,
        ]);
    }

    #[Route('/avisos/{id}/hide', name: 'admin_community_classified_hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function hideClassified(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $ad = $em->find(ClassifiedAd::class, $id);
        if ($ad === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('hide_classified'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $ad->hide();
        $em->flush();
        $this->addFlash('success', 'Anuncio ocultado.');

        return $this->redirectToRoute('admin_community_classifieds');
    }

    #[Route('/avisos/{id}/restore', name: 'admin_community_classified_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restoreClassified(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $ad = $em->find(ClassifiedAd::class, $id);
        if ($ad === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('restore_classified'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $ad->restore();
        $em->flush();
        $this->addFlash('success', 'Anuncio restaurado.');

        return $this->redirectToRoute('admin_community_classifieds');
    }

    #[Route('/foro', name: 'admin_community_forum', methods: ['GET'])]
    public function forum(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $topicRepo = $em->getRepository(ForumTopic::class);
        $postRepo = $em->getRepository(ForumPost::class);
        $topics = $topicRepo instanceof ForumTopicRepository ? $topicRepo->findRecentForAdmin(60) : [];
        $posts = $postRepo instanceof ForumPostRepository ? $postRepo->findRecentForAdmin(40) : [];

        return $this->render('admin/community/forum.html.twig', [
            'site' => $site,
            'topics' => $topics,
            'posts' => $posts,
        ]);
    }

    #[Route('/foro/tema/{id}/hide', name: 'admin_community_topic_hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function hideTopic(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $topic = $em->find(ForumTopic::class, $id);
        if ($topic === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('hide_topic'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $topic->hide();
        $em->flush();
        $this->addFlash('success', 'Tema ocultado.');

        return $this->redirectToRoute('admin_community_forum');
    }

    #[Route('/foro/tema/{id}/restore', name: 'admin_community_topic_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restoreTopic(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $topic = $em->find(ForumTopic::class, $id);
        if ($topic === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('restore_topic'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $topic->restore();
        $em->flush();
        $this->addFlash('success', 'Tema restaurado.');

        return $this->redirectToRoute('admin_community_forum');
    }

    #[Route('/foro/tema/{id}/lock', name: 'admin_community_topic_lock', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function lockTopic(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $topic = $em->find(ForumTopic::class, $id);
        if ($topic === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('lock_topic'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $topic->lock();
        $em->flush();
        $this->addFlash('success', 'Tema cerrado.');

        return $this->redirectToRoute('admin_community_forum');
    }

    #[Route('/foro/tema/{id}/unlock', name: 'admin_community_topic_unlock', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unlockTopic(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $topic = $em->find(ForumTopic::class, $id);
        if ($topic === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('unlock_topic'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $topic->unlock();
        $em->flush();
        $this->addFlash('success', 'Tema reabierto.');

        return $this->redirectToRoute('admin_community_forum');
    }

    #[Route('/foro/respuesta/{id}/hide', name: 'admin_community_post_hide', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function hidePost(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $post = $em->find(ForumPost::class, $id);
        if ($post === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('hide_post'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $post->hide();
        $em->flush();
        $this->addFlash('success', 'Respuesta ocultada.');

        return $this->redirectToRoute('admin_community_forum');
    }

    #[Route('/foro/respuesta/{id}/restore', name: 'admin_community_post_restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restorePost(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $post = $em->find(ForumPost::class, $id);
        if ($post === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('restore_post'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $post->restore();
        $em->flush();
        $this->addFlash('success', 'Respuesta restaurada.');

        return $this->redirectToRoute('admin_community_forum');
    }
}
