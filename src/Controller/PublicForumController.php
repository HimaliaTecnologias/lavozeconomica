<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Platform\User;
use App\Entity\Site\ForumCategory;
use App\Entity\Site\ForumPost;
use App\Entity\Site\ForumTopic;
use App\Editorial\CommunityRichTextSanitizer;
use App\Editorial\CommunitySlugAllocator;
use App\Editorial\PublicRelatedNewsProvider;
use App\Repository\Site\ForumCategoryRepository;
use App\Repository\Site\ForumPostRepository;
use App\Repository\Site\ForumTopicRepository;
use App\Security\AbuseRateLimiter;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PublicForumController extends AbstractController
{
    #[Route('/foro', name: 'public_forum', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        PublicRelatedNewsProvider $relatedNewsProvider,
    ): Response {
        return $this->renderForumIndex(
            $holder,
            $pageContextFactory,
            $settingsReader,
            $siteEmFactory,
            $relatedNewsProvider,
            null,
            'Foro de la comunidad — ',
            'Debate sobre actualidad y temas de interés con otros lectores de ',
        );
    }

    #[Route('/foro/categoria/{categorySlug}', name: 'public_forum_category', methods: ['GET'])]
    public function category(
        string $categorySlug,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        PublicRelatedNewsProvider $relatedNewsProvider,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getForumConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('El foro no está disponible.');
        }

        $em = $siteEmFactory->forContext($site);
        $category = $this->forumCategoryRepository($em)->findActiveBySlug($categorySlug);
        if ($category === null) {
            throw $this->createNotFoundException('Categoría no encontrada.');
        }

        return $this->renderForumIndex(
            $holder,
            $pageContextFactory,
            $settingsReader,
            $siteEmFactory,
            $relatedNewsProvider,
            $category,
            $category->getName().' — Foro — ',
            $category->getDescription() ?? 'Temas de '.$category->getName().' en el foro de ',
        );
    }

    #[Route('/foro/nuevo', name: 'public_forum_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function createTopic(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        SiteAuthorizationChecker $authorizationChecker,
        CommunitySlugAllocator $slugAllocator,
        CommunityRichTextSanitizer $richTextSanitizer,
        AbuseRateLimiter $abuseRateLimiter,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getForumConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('El foro no está disponible.');
        }

        $user = $this->requireReader($site, $authorizationChecker);
        $pageContext = $pageContextFactory->build($site);
        $em = $siteEmFactory->forContext($site);
        $categories = $this->forumCategoryRepository($em)->findActiveOrdered();

        $formData = [
            'title' => '',
            'body' => '',
            'categoryId' => $request->query->getInt('categoria', 0) ?: '',
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forum_topic_create', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $userId = $user->getId();
            if ($userId === null) {
                throw $this->createAccessDeniedException();
            }
            $abuseRateLimiter->enforceUgc($request, 'forum-topic', (string) $userId);

            $rawBody = trim((string) $request->request->get('body', ''));
            $formData = [
                'title' => trim((string) $request->request->get('title', '')),
                'body' => $rawBody,
                'categoryId' => (string) $request->request->get('categoryId', ''),
            ];

            $category = $em->find(ForumCategory::class, (int) $formData['categoryId']);
            $errors = [];
            if ($category === null || !$category->isActive()) {
                $errors['categoryId'] = 'Selecciona una categoría válida.';
            }
            if ($formData['title'] === '' || strlen($formData['title']) > 200) {
                $errors['title'] = 'El título debe tener entre 1 y 200 caracteres.';
            }
            $bodyLength = $richTextSanitizer->plainTextLength($rawBody);
            if ($richTextSanitizer->isEffectivelyEmpty($rawBody) || $bodyLength > 8000) {
                $errors['body'] = 'El mensaje debe tener entre 1 y 8000 caracteres.';
            }
            $body = $richTextSanitizer->sanitize($rawBody);

            if ($errors === [] && $category !== null) {
                $userId = $user->getId();
                if ($userId === null) {
                    throw $this->createAccessDeniedException();
                }

                $slug = $slugAllocator->forForumTopic($em, $formData['title']);
                $topic = new ForumTopic(
                    $formData['title'],
                    $slug,
                    $body,
                    $userId,
                    $user->getName(),
                    $category,
                );
                $em->persist($topic);
                $em->flush();

                $this->addFlash('success', 'Tema creado. Ya puedes recibir respuestas.');

                return $this->redirectToRoute('public_forum_show', ['slug' => $slug]);
            }

            return $this->render('public/forum/form.html.twig', $this->forumFormView(
                $site,
                $pageContext,
                $formData,
                $errors,
                $categories,
            ));
        }

        return $this->render('public/forum/form.html.twig', $this->forumFormView(
            $site,
            $pageContext,
            $formData,
            [],
            $categories,
        ));
    }

    #[Route('/foro/{slug}', name: 'public_forum_show', methods: ['GET'])]
    public function show(
        string $slug,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        PublicRelatedNewsProvider $relatedNewsProvider,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getForumConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('El foro no está disponible.');
        }

        $em = $siteEmFactory->forContext($site);
        $topic = $this->topicRepository($em)->findVisibleBySlug($slug);
        if ($topic === null) {
            throw $this->createNotFoundException('Tema no encontrado.');
        }

        $posts = $this->postRepository($em)->findVisibleForTopic($topic);
        $pageContext = $pageContextFactory->build($site);
        $categories = $this->forumCategoryRepository($em)->findActiveOrdered();
        $relatedNews = $relatedNewsProvider->forCommunityPage($site);

        return $this->render('public/forum/show.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'topic' => $topic,
            'posts' => $posts,
            'categories' => $categories,
            'latestNews' => $relatedNews['latestNews'],
            'interestNews' => $relatedNews['interestNews'],
            'seoTitle' => $topic->getTitle().' — Foro — '.$site->name,
            'seoDescription' => mb_substr(strip_tags($topic->getBody()), 0, 160),
        ]);
    }

    #[Route('/foro/{slug}/responder', name: 'public_forum_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reply(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        SiteAuthorizationChecker $authorizationChecker,
        CommunityRichTextSanitizer $richTextSanitizer,
        AbuseRateLimiter $abuseRateLimiter,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getForumConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException();
        }

        $user = $this->requireReader($site, $authorizationChecker);
        $userId = $user->getId();
        if ($userId === null) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('forum_reply_'.$slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $abuseRateLimiter->enforceUgc($request, 'forum-reply', (string) $userId);

        $em = $siteEmFactory->forContext($site);
        $topic = $this->topicRepository($em)->findVisibleBySlug($slug);
        if ($topic === null) {
            throw $this->createNotFoundException();
        }

        if ($topic->isLocked()) {
            $this->addFlash('error', 'Este tema está cerrado y no admite nuevas respuestas.');

            return $this->redirectToRoute('public_forum_show', ['slug' => $slug]);
        }

        $rawBody = trim((string) $request->request->get('body', ''));
        $bodyLength = $richTextSanitizer->plainTextLength($rawBody);
        if ($richTextSanitizer->isEffectivelyEmpty($rawBody) || $bodyLength > 4000) {
            $this->addFlash('error', 'La respuesta debe tener entre 1 y 4000 caracteres.');

            return $this->redirectToRoute('public_forum_show', ['slug' => $slug]);
        }

        $body = $richTextSanitizer->sanitize($rawBody);
        $post = new ForumPost($topic, $userId, $user->getName(), $body);
        $topic->registerReply();
        $em->persist($post);
        $em->flush();

        $this->addFlash('success', 'Respuesta publicada.');

        return $this->redirectToRoute('public_forum_show', ['slug' => $slug]);
    }

    private function renderForumIndex(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        PublicRelatedNewsProvider $relatedNewsProvider,
        ?ForumCategory $activeCategory,
        string $titlePrefix,
        string $descriptionPrefix,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getForumConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('El foro no está disponible.');
        }

        $em = $siteEmFactory->forContext($site);
        $topics = $this->topicRepository($em)->findVisibleLatest($activeCategory);
        $categories = $this->forumCategoryRepository($em)->findActiveOrdered();
        $pageContext = $pageContextFactory->build($site);
        $relatedNews = $relatedNewsProvider->forCommunityPage($site);

        return $this->render('public/forum/index.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'topics' => $topics,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'latestNews' => $relatedNews['latestNews'],
            'interestNews' => $relatedNews['interestNews'],
            'seoTitle' => $titlePrefix.$site->name,
            'seoDescription' => $descriptionPrefix.$site->name,
        ]);
    }

    /** @param array<string, mixed> $pageContext */
    private function forumFormView(
        SiteContext $site,
        array $pageContext,
        array $formData,
        array $errors,
        array $categories,
    ): array {
        return [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'formData' => $formData,
            'errors' => $errors,
            'categories' => $categories,
            'seoTitle' => 'Nuevo tema — Foro — '.$site->name,
        ];
    }

    private function requireReader(SiteContext $site, SiteAuthorizationChecker $authorizationChecker): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$authorizationChecker->canEngageAsReader($user, $site)) {
            throw $this->createAccessDeniedException('Solo los lectores registrados pueden participar en el foro.');
        }

        return $user;
    }

    private function topicRepository(EntityManagerInterface $em): ForumTopicRepository
    {
        $repo = $em->getRepository(ForumTopic::class);
        if (!$repo instanceof ForumTopicRepository) {
            throw new \RuntimeException('ForumTopicRepository no disponible.');
        }

        return $repo;
    }

    private function postRepository(EntityManagerInterface $em): ForumPostRepository
    {
        $repo = $em->getRepository(ForumPost::class);
        if (!$repo instanceof ForumPostRepository) {
            throw new \RuntimeException('ForumPostRepository no disponible.');
        }

        return $repo;
    }

    private function forumCategoryRepository(EntityManagerInterface $em): ForumCategoryRepository
    {
        $repo = $em->getRepository(ForumCategory::class);
        if (!$repo instanceof ForumCategoryRepository) {
            throw new \RuntimeException('ForumCategoryRepository no disponible.');
        }

        return $repo;
    }
}
