<?php

namespace App\Controller\Admin;

use App\Admin\AdminActivityLogger;
use App\Ai\ArticleAiImproveService;
use App\Cache\PublicArticleCache;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Site\Article;
use App\Entity\Site\ArticleImage;
use App\Entity\Site\Category;
use App\Entity\Site\Gallery;
use App\Entity\Site\Media;
use App\Entity\Site\Poll;
use App\Repository\Site\ArticleRepository;
use App\Repository\Site\CategoryRepository;
use App\Repository\Site\GalleryRepository;
use App\Repository\Site\PollRepository;
use App\Entity\Site\Enum\ArticleStatus;
use App\Editorial\ArticleFeaturedImageGuard;
use App\Editorial\ArticleImageEnhancementDispatcher;
use App\Editorial\ArticleImageSyncService;
use App\Editorial\ArticleRelatedSyncService;
use App\Editorial\ArticleTagSyncService;
use App\Editorial\GallerySyncService;
use App\Editorial\PollSyncService;
use App\Editorial\SlugGenerator;
use App\Entity\Platform\Site;
use App\Entity\Platform\User;
use App\Entity\Platform\UserSiteRole;
use App\Security\SiteAuthorizationChecker;
use App\Security\SiteRole;
use App\Security\UserSiteRoleQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/articles')]
#[IsGranted('ROLE_USER')]
final class ArticleController extends AbstractAdminController
{
    private const DEFAULT_PER_PAGE = 25;

    /** @var list<int> */
    private const ALLOWED_PER_PAGE = [25, 50, 100];

    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly SlugGenerator $slugGenerator,
        private readonly ArticleImageSyncService $imageSyncService,
        private readonly ArticleTagSyncService $tagSyncService,
        private readonly ArticleRelatedSyncService $relatedSyncService,
        private readonly ArticleImageEnhancementDispatcher $imageEnhancementDispatcher,
        private readonly ArticleFeaturedImageGuard $featuredImageGuard,
        private readonly PollSyncService $pollSync,
        private readonly GallerySyncService $gallerySync,
        private readonly AdminActivityLogger $activityLogger,
        private readonly UserSiteRoleQuery $userSiteRoleQuery,
        private readonly PublicArticleCache $articleCache,
        private readonly SiteSettingsReader $settingsReader,
        private readonly ArticleAiImproveService $articleAiImproveService,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_articles_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$site] = $this->requireArticleEditorAccess();
        $em = $this->siteEmFactory->forContext($site);
        $repo = $em->getRepository(Article::class);

        $q = trim((string) $request->query->get('q', ''));
        $statusRaw = (string) $request->query->get('status', '');
        $status = $statusRaw !== '' ? ArticleStatus::tryFrom($statusRaw) : null;
        $categoryId = $request->query->get('category');
        $categoryFilter = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
        $sourceRaw = (string) $request->query->get('source', '');
        $source = match ($sourceRaw) {
            'ingest', 'manual' => $sourceRaw,
            default => null,
        };
        $sort = (string) $request->query->get('sort', 'created');
        if (!\in_array($sort, ['created', 'updated', 'published', 'title', 'views'], true)) {
            $sort = 'created';
        }
        $perPageRaw = (int) $request->query->get('per_page', self::DEFAULT_PER_PAGE);
        $perPage = \in_array($perPageRaw, self::ALLOWED_PER_PAGE, true) ? $perPageRaw : self::DEFAULT_PER_PAGE;
        $page = max(1, (int) $request->query->get('page', 1));

        $categoryIds = $this->resolveCategoryFilterIds($em, $categoryFilter);

        if (!$repo instanceof ArticleRepository) {
            $articles = $repo->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);
            $totalArticles = \count($articles);
            $totalPages = 1;
            $statusCounts = [];
        } else {
            $result = $repo->findForAdminListPaginated(
                $q !== '' ? $q : null,
                $status,
                $categoryIds,
                $source,
                $sort,
                $page,
                $perPage,
            );
            $totalArticles = $result['total'];
            $totalPages = max(1, (int) ceil($totalArticles / $perPage));

            if ($page > $totalPages) {
                return $this->redirectToRoute('admin_articles_index', $this->buildArticlesIndexRouteParams(
                    $q,
                    $statusRaw,
                    $categoryFilter,
                    $source,
                    $sort,
                    $perPage,
                    $totalPages,
                ));
            }

            $articles = $result['items'];
            $statusCounts = $repo->countAdminListByStatus(
                $q !== '' ? $q : null,
                $categoryIds,
                $source,
            );
        }

        $listRouteParams = $this->buildArticlesIndexRouteParams(
            $q,
            $statusRaw,
            $categoryFilter,
            $source,
            $sort,
            $perPage,
            null,
        );

        $articleImageIssues = [];
        foreach ($articles as $article) {
            $issue = $this->featuredImageGuard->getIssue($article);
            if ($issue !== null && $article->getId() !== null) {
                $articleImageIssues[$article->getId()] = $issue;
            }
        }

        return $this->render('admin/article/index.html.twig', [
            'site' => $site,
            'articles' => $articles,
            'categories' => $this->loadCategories($em),
            'filters' => [
                'q' => $q,
                'status' => $statusRaw,
                'category' => $categoryFilter,
                'source' => $sourceRaw,
                'sort' => $sort,
                'per_page' => $perPage,
            ],
            'page' => $page,
            'totalPages' => $totalPages,
            'totalArticles' => $totalArticles,
            'perPage' => $perPage,
            'statusCounts' => $statusCounts,
            'listRouteParams' => $listRouteParams,
            'articleImageIssues' => $articleImageIssues,
        ]);
    }

    #[Route('/bulk', name: 'admin_articles_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        [$site, $user] = $this->requireArticleEditorAccess();
        $em = $this->siteEmFactory->forContext($site);

        if (!$this->isCsrfTokenValid('articles_bulk', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $action = (string) $request->request->get('action', '');
        $ids = $this->parseBulkIds($request->request->all('ids'));
        if ($ids === []) {
            $this->addFlash('error', 'Selecciona al menos una noticia.');

            return $this->redirectToArticlesIndex($request);
        }

        $allowed = ['trash', 'draft', 'pending_review', 'publish', 'delete'];
        if (!\in_array($action, $allowed, true)) {
            $this->addFlash('error', 'Acción en lote no válida.');

            return $this->redirectToArticlesIndex($request);
        }

        $articles = $em->getRepository(Article::class)->findBy(['id' => $ids]);
        if ($articles === []) {
            $this->addFlash('error', 'No se encontraron las noticias seleccionadas.');

            return $this->redirectToArticlesIndex($request);
        }

        $done = 0;
        $skipped = [];

        foreach ($articles as $article) {
            switch ($action) {
                case 'trash':
                    if ($article->getStatus() === ArticleStatus::Archived) {
                        break;
                    }
                    $article->setStatus(ArticleStatus::Archived);
                    ++$done;
                    break;
                case 'draft':
                    $article->setStatus(ArticleStatus::Draft);
                    $article->setScheduledAt(null);
                    ++$done;
                    break;
                case 'pending_review':
                    $article->setStatus(ArticleStatus::PendingReview);
                    $article->setScheduledAt(null);
                    ++$done;
                    break;
                case 'publish':
                    if ($article->getStatus() === ArticleStatus::Published) {
                        break;
                    }
                    if (!$this->featuredImageGuard->hasUsableHeroVisual($article)) {
                        $issue = $this->featuredImageGuard->getIssue($article) ?? 'Sin imagen destacada.';
                        $skipped[] = $article->getTitle().' ('.$issue.')';
                        break;
                    }
                    if ($article->getPublishedAt() === null) {
                        $article->publish();
                    } else {
                        $article->setStatus(ArticleStatus::Published);
                    }
                    $article->setScheduledAt(null);
                    ++$done;
                    break;
                case 'delete':
                    $em->remove($article);
                    ++$done;
                    break;
            }
        }

        if ($done > 0) {
            $this->activityLogger->log(
                $em,
                $user,
                'acción masiva: '.$this->bulkActionLabel($action),
                'article',
                null,
                $done.' noticias',
            );
            $em->flush();
            $this->articleCache->invalidateSite($site);
            $this->addFlash('success', $this->bulkSuccessMessage($action, $done));
        } elseif ($skipped === []) {
            $this->addFlash('info', 'Ninguna noticia cambió con esa acción.');
        }

        if ($skipped !== []) {
            $this->addFlash('error', 'No se pudieron publicar: '.implode(' · ', array_slice($skipped, 0, 5)).(\count($skipped) > 5 ? '…' : ''));
        }

        return $this->redirectToArticlesIndex($request);
    }

    /**
     * @return list<int>|null
     */
    private function resolveCategoryFilterIds(EntityManagerInterface $em, ?int $categoryFilter): ?array
    {
        if ($categoryFilter === null) {
            return null;
        }

        $categoryRepo = $em->getRepository(Category::class);
        if (!$categoryRepo instanceof CategoryRepository) {
            return [$categoryFilter];
        }

        $category = $categoryRepo->find($categoryFilter);
        if ($category === null) {
            return [$categoryFilter];
        }

        return $categoryRepo->collectSelfAndDescendantIds($category);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildArticlesIndexRouteParams(
        string $q,
        string $statusRaw,
        ?int $categoryFilter,
        ?string $source,
        string $sort,
        int $perPage,
        ?int $page,
    ): array {
        $params = [];

        if ($q !== '') {
            $params['q'] = $q;
        }
        if ($statusRaw !== '') {
            $params['status'] = $statusRaw;
        }
        if ($categoryFilter !== null) {
            $params['category'] = $categoryFilter;
        }
        if ($source !== null) {
            $params['source'] = $source;
        }
        if ($sort !== 'created') {
            $params['sort'] = $sort;
        }
        if ($perPage !== self::DEFAULT_PER_PAGE) {
            $params['per_page'] = $perPage;
        }
        if ($page !== null && $page > 1) {
            $params['page'] = $page;
        }

        return $params;
    }

    /**
     * @param mixed $raw
     *
     * @return list<int>
     */
    private function parseBulkIds(mixed $raw): array
    {
        if (!\is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        return \array_slice($ids, 0, 200);
    }

    private function redirectToArticlesIndex(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $statusRaw = (string) $request->query->get('status', '');
        $categoryId = $request->query->get('category');
        $categoryFilter = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
        $sourceRaw = (string) $request->query->get('source', '');
        $source = match ($sourceRaw) {
            'ingest', 'manual' => $sourceRaw,
            default => null,
        };
        $sort = (string) $request->query->get('sort', 'created');
        if (!\in_array($sort, ['created', 'updated', 'published', 'title', 'views'], true)) {
            $sort = 'created';
        }
        $perPageRaw = (int) $request->query->get('per_page', self::DEFAULT_PER_PAGE);
        $perPage = \in_array($perPageRaw, self::ALLOWED_PER_PAGE, true) ? $perPageRaw : self::DEFAULT_PER_PAGE;
        $page = max(1, (int) $request->query->get('page', 1));

        return $this->redirectToRoute('admin_articles_index', $this->buildArticlesIndexRouteParams(
            $q,
            $statusRaw,
            $categoryFilter,
            $source,
            $sort,
            $perPage,
            $page,
        ));
    }

    private function bulkActionLabel(string $action): string
    {
        return match ($action) {
            'trash' => 'papelera',
            'draft' => 'borrador',
            'pending_review' => 'en revisión',
            'publish' => 'publicar',
            'delete' => 'eliminar',
            default => $action,
        };
    }

    private function bulkSuccessMessage(string $action, int $count): string
    {
        $noun = $count === 1 ? 'noticia' : 'noticias';

        return match ($action) {
            'trash' => sprintf('%d %s movida%s a la papelera.', $count, $noun, $count === 1 ? '' : 's'),
            'draft' => sprintf('%d %s pasada%s a borrador.', $count, $noun, $count === 1 ? '' : 's'),
            'pending_review' => sprintf('%d %s marcada%s en revisión.', $count, $noun, $count === 1 ? '' : 's'),
            'publish' => sprintf('%d %s publicada%s.', $count, $noun, $count === 1 ? '' : 's'),
            'delete' => sprintf('%d %s eliminada%s definitivamente.', $count, $noun, $count === 1 ? '' : 's'),
            default => sprintf('%d %s actualizada%s.', $count, $noun, $count === 1 ? '' : 's'),
        };
    }

    private function loadCategories(EntityManagerInterface $em): array
    {
        $repo = $em->getRepository(Category::class);
        if ($repo instanceof CategoryRepository) {
            return $repo->findRootsForAdmin();
        }

        return $repo->findBy([], ['name' => 'ASC']);
    }

    #[Route('/new', name: 'admin_articles_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        [$site, $user] = $this->requireArticleEditorAccess();
        $em = $this->siteEmFactory->forContext($site);

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $em, $site, $user, null);
        }

        return $this->render('admin/article/form.html.twig', [
            'site' => $site,
            'article' => null,
            'categories' => $this->loadCategories($em),
            'polls' => $this->loadPolls($em),
            'galleries' => $this->loadGalleries($em),
            'authors' => $this->loadAuthorChoices($site, $user),
            'formData' => [
                'authorUserId' => $user->getId(),
                'authorName' => $user->getName(),
            ],
            'errors' => [],
            'suggestedTags' => [],
            'extraTagsValue' => '',
            'selectedRelated' => [],
            ...$this->articleFormExtras($site),
        ]);
    }

    #[Route('/ai-improve', name: 'admin_articles_ai_improve', methods: ['POST'])]
    public function aiImprove(Request $request): JsonResponse
    {
        [$site] = $this->requireArticleEditorAccess();

        $payload = [];
        $contentType = (string) $request->headers->get('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            try {
                $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
                $payload = \is_array($decoded) ? $decoded : [];
            } catch (\JsonException) {
                return new JsonResponse(['error' => 'JSON inválido.'], Response::HTTP_BAD_REQUEST);
            }
        } else {
            $payload = $request->request->all();
        }

        $token = (string) ($payload['_token'] ?? '');
        if (!$this->isCsrfTokenValid('article_ai_improve', $token)) {
            return new JsonResponse(['error' => 'Token CSRF inválido.'], Response::HTTP_FORBIDDEN);
        }

        if (!$this->settingsReader->getAiConfig($site)->isConfigured()) {
            return new JsonResponse(['error' => 'Configura la API key de OpenAI en Configuración → IA.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $improved = $this->articleAiImproveService->improve(
                $site,
                (string) ($payload['title'] ?? ''),
                (string) ($payload['subtitle'] ?? ''),
                (string) ($payload['lead'] ?? ''),
                (string) ($payload['bodyHtml'] ?? ''),
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'No se pudo mejorar la noticia con IA. Revisa la clave o inténtalo de nuevo.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['ok' => true, 'article' => $improved]);
    }

    #[Route('/search.json', name: 'admin_articles_search_json', methods: ['GET'])]
    public function searchJson(Request $request): JsonResponse
    {
        [$site] = $this->requireArticleEditorAccess();
        $em = $this->siteEmFactory->forContext($site);
        $repo = $em->getRepository(Article::class);
        if (!$repo instanceof ArticleRepository) {
            return new JsonResponse(['items' => []]);
        }

        $q = trim((string) $request->query->get('q', ''));
        $excludeId = $request->query->get('exclude');
        $exclude = $excludeId !== null && $excludeId !== '' ? (int) $excludeId : null;
        $items = $repo->searchForRelatedPicker($q, 12, $exclude);

        return new JsonResponse([
            'items' => array_map(static function (Article $article): array {
                $publishedAt = $article->getPublishedAt();

                return [
                    'id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'status' => $article->getStatus()->value,
                    'category' => $article->getCategory()?->getName(),
                    'publishedAt' => $publishedAt?->format('d/m/Y'),
                ];
            }, $items),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_articles_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        [$site, $user] = $this->requireArticleEditorAccess();
        $em = $this->siteEmFactory->forContext($site);
        $article = $this->findArticleOr404($em, $id);

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $em, $site, $user, $article);
        }

        return $this->render('admin/article/form.html.twig', [
            'site' => $site,
            'article' => $article,
            'categories' => $this->loadCategories($em),
            'polls' => $this->loadPolls($em),
            'galleries' => $this->loadGalleries($em),
            'authors' => $this->loadAuthorChoices($site, $user),
            'formData' => [],
            'errors' => [],
            'suggestedTags' => $this->tagSyncService->suggestForArticle($article),
            'extraTagsValue' => $this->tagSyncService->extraTagsInputForArticle($article),
            'selectedRelated' => $article->getRelatedArticles(),
            ...$this->articleFormExtras($site),
        ]);
    }

    #[Route('/{id}/publish', name: 'admin_articles_publish', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function publish(Request $request, int $id): Response
    {
        [$site, $user] = $this->requireArticleEditorAccess();
        $em = $this->siteEmFactory->forContext($site);
        $article = $this->findArticleOr404($em, $id);

        if (!$this->isCsrfTokenValid('publish'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $article->publish();
        $this->activityLogger->log($em, $user, 'publicó noticia', 'article', $id, $article->getTitle());
        $em->flush();
        $this->articleCache->invalidateSite($site);

        $this->addFlash('success', 'Artículo publicado.');

        return $this->redirectToRoute('admin_articles_index');
    }

    #[Route('/{id}/duplicate', name: 'admin_articles_duplicate', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicate(Request $request, int $id): Response
    {
        [$site, $user] = $this->requireArticleEditorAccess();
        $em = $this->siteEmFactory->forContext($site);
        $original = $this->findArticleOr404($em, $id);

        if (!$this->isCsrfTokenValid('duplicate'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $title = 'Copia de '.$original->getTitle();
        $slug = $this->slugGenerator->fromTitle($title);
        $copy = new Article($title, $original->getBodyHtml(), $slug, ArticleStatus::Draft);
        $copy->setSubtitle($original->getSubtitle())
            ->setLead($original->getLead())
            ->setCategory($original->getCategory())
            ->setPoll($original->getPoll())
            ->setGallery($original->getGallery())
            ->setShowOnHome($original->isShowOnHome())
            ->setAuthorName($original->getAuthorName())
            ->setAuthorUserId($original->getAuthorUserId());

        foreach ($original->getImages() as $image) {
            $newImage = new ArticleImage($copy, $image->getUrl(), $image->getSortOrder());
            $newImage->setAlt($image->getAlt())
                ->setCaption($image->getCaption())
                ->setSourceUrl($image->getSourceUrl())
                ->setIsFeatured($image->isFeatured());
            if ($image->getMedia() !== null) {
                $newImage->setMedia($image->getMedia());
            }
            $copy->addImage($newImage);
            $em->persist($newImage);
        }

        foreach ($original->getTags() as $tag) {
            $copy->addTag($tag);
        }

        $em->persist($copy);
        $this->activityLogger->log($em, $user, 'duplicó noticia', 'article', $original->getId(), $original->getTitle());
        $em->flush();

        $this->addFlash('success', 'Noticia duplicada como borrador.');

        $copyId = $copy->getId();
        if ($copyId === null) {
            return $this->redirectToRoute('admin_articles_index');
        }

        return $this->redirectToRoute('admin_articles_edit', ['id' => $copyId]);
    }

    #[Route('/{id}/delete', name: 'admin_articles_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        [$site, $user] = $this->requireArticleEditorAccess();
        $em = $this->siteEmFactory->forContext($site);
        $article = $this->findArticleOr404($em, $id);

        if (!$this->isCsrfTokenValid('delete'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $title = $article->getTitle();
        $em->remove($article);
        $this->activityLogger->log($em, $user, 'eliminó noticia', 'article', $id, $title);
        $em->flush();
        $this->articleCache->invalidateSite($site);

        $this->addFlash('success', 'Artículo eliminado.');

        return $this->redirectToRoute('admin_articles_index');
    }

    private function handleSave(Request $request, EntityManagerInterface $em, SiteContext $site, \App\Entity\Platform\User $user, ?Article $article): Response
    {
        if (!$this->isCsrfTokenValid('article_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $title = trim((string) $request->request->get('title', ''));
        $bodyHtml = trim((string) $request->request->get('bodyHtml', ''));
        $slug = trim((string) $request->request->get('slug', ''));
        $subtitle = trim((string) $request->request->get('subtitle', '')) ?: null;
        $lead = trim((string) $request->request->get('lead', '')) ?: null;
        $statusValue = (string) $request->request->get('status', ArticleStatus::Draft->value);
        $categoryId = $request->request->get('categoryId');
        $scheduledAtRaw = trim((string) $request->request->get('scheduledAt', ''));
        $agendaStartsAtRaw = trim((string) $request->request->get('agendaStartsAt', ''));
        $agendaEndsAtRaw = trim((string) $request->request->get('agendaEndsAt', ''));

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'El título es obligatorio.';
        }
        if ($this->isBodyHtmlEmpty($bodyHtml)) {
            $errors['bodyHtml'] = 'El cuerpo es obligatorio.';
        }

        $status = ArticleStatus::tryFrom($statusValue) ?? ArticleStatus::Draft;

        $featuredPayload = $request->request->all('featured');
        $featuredUrl = \is_array($featuredPayload) ? trim((string) ($featuredPayload['url'] ?? '')) : '';
        $featuredMediaId = \is_array($featuredPayload) ? trim((string) ($featuredPayload['mediaId'] ?? '')) : '';
        $requiresFeatured = \in_array($status, [ArticleStatus::Published, ArticleStatus::Scheduled], true);
        if ($requiresFeatured && $featuredUrl === '' && $featuredMediaId === '') {
            $errors['featured'] = 'La imagen principal es obligatoria para publicar o programar la noticia.';
        }

        if ($slug === '') {
            $slug = $this->slugGenerator->fromTitle($title);
        }

        $category = null;
        if ($categoryId !== null && $categoryId !== '') {
            $category = $em->find(Category::class, (int) $categoryId);
        }

        if ($errors !== []) {
            return $this->renderArticleForm($em, $site, $article, $errors, $request->request->all(), $this->articleFormExtraContext($em, $article, $request, $title, $bodyHtml, $slug, $subtitle, $lead, $category));
        }

        $isNew = $article === null;

        if ($isNew) {
            $article = new Article($title, $bodyHtml, $slug, $status);
            $article->setSubtitle($subtitle)->setLead($lead)->setCategory($category);
            $em->persist($article);
        } else {
            $article->setTitle($title)
                ->setBodyHtml($bodyHtml)
                ->setSlug($slug)
                ->setSubtitle($subtitle)
                ->setLead($lead)
                ->setCategory($category);
        }

        $this->applyAuthorFromRequest($request, $site, $article);

        if ($status === ArticleStatus::Scheduled) {
            $scheduledAt = null;
            if ($scheduledAtRaw !== '') {
                try {
                    $scheduledAt = new \DateTimeImmutable($scheduledAtRaw);
                } catch (\Throwable) {
                    $scheduledAt = null;
                }
            }
            if ($scheduledAt === null) {
                $errors['scheduledAt'] = 'Indica fecha y hora de publicación.';
            } elseif ($scheduledAt <= new \DateTimeImmutable()) {
                $errors['scheduledAt'] = 'La fecha de programación debe ser posterior a ahora. Para publicar ya, elige «Publicar ahora».';
            }
            if ($errors !== []) {
                return $this->renderArticleForm($em, $site, $article, $errors, $request->request->all(), $this->articleFormExtraContext($em, $article, $request, $title, $bodyHtml, $slug, $subtitle, $lead, $category));
            }
            $article->schedule($scheduledAt);
        } elseif ($status === ArticleStatus::Published) {
            if ($article->getPublishedAt() === null) {
                $article->publish();
            } else {
                $article->setStatus(ArticleStatus::Published);
            }
            $article->setScheduledAt(null);
        } else {
            $article->setStatus($status);
            if ($status !== ArticleStatus::Scheduled) {
                $article->setScheduledAt(null);
            }
        }

        $this->syncArticlePoll($request, $em, $article);
        $this->syncArticleGallery($request, $em, $article);
        $this->syncArticleVideo($request, $em, $article);

        $article->setShowOnHome($request->request->has('showOnHome'));
        $article->setPinned($request->request->has('isPinned'));

        $agendaStartsAt = $this->parseAgendaDate($agendaStartsAtRaw);
        $agendaEndsAt = $this->parseAgendaDate($agendaEndsAtRaw);
        if (($agendaStartsAtRaw !== '' && $agendaStartsAt === null) || ($agendaEndsAtRaw !== '' && $agendaEndsAt === null)) {
            if ($agendaStartsAtRaw !== '' && $agendaStartsAt === null) {
                $errors['agendaStartsAt'] = 'Fecha de inicio no válida.';
            }
            if ($agendaEndsAtRaw !== '' && $agendaEndsAt === null) {
                $errors['agendaEndsAt'] = 'Fecha de fin no válida.';
            }
        } elseif (($agendaStartsAt === null) !== ($agendaEndsAt === null)) {
            $errors['agendaEndsAt'] = 'Indica inicio y fin de vigencia, o deja ambos vacíos.';
        } elseif ($agendaStartsAt !== null && $agendaEndsAt !== null && $agendaEndsAt < $agendaStartsAt) {
            $errors['agendaEndsAt'] = 'El fin de vigencia debe ser posterior al inicio.';
        }
        if ($errors !== []) {
            return $this->renderArticleForm($em, $site, $article, $errors, $request->request->all(), $this->articleFormExtraContext($em, $article, $request, $title, $bodyHtml, $slug, $subtitle, $lead, $category));
        }
        $article->setAgendaStartsAt($agendaStartsAt)->setAgendaEndsAt($agendaEndsAt);

        $featured = $request->request->all('featured');
        $this->imageSyncService->syncFeaturedFromForm(
            $em,
            $article,
            \is_array($featured) ? $featured : null,
        );

        // Tras sincronizar la imagen: no dejar publicada/programada si el hero no es usable.
        if (\in_array($status, [ArticleStatus::Published, ArticleStatus::Scheduled], true)
            && !$this->featuredImageGuard->hasUsableHeroVisual($article)
        ) {
            $errors['featured'] = $this->featuredImageGuard->getIssue($article)
                ?? 'La imagen principal es obligatoria y debe estar disponible.';
            $article->setStatus(ArticleStatus::Draft);
            $article->setScheduledAt(null);

            return $this->renderArticleForm(
                $em,
                $site,
                $article,
                $errors,
                $request->request->all(),
                $this->articleFormExtraContext($em, $article, $request, $title, $bodyHtml, $slug, $subtitle, $lead, $category),
            );
        }

        $tagsInput = (string) $request->request->get('extraTags', '');
        $this->tagSyncService->syncForArticle($em, $article, $tagsInput);

        $relatedIds = $request->request->all('relatedIds');
        $this->relatedSyncService->syncForArticle(
            $em,
            $article,
            \is_array($relatedIds) ? $relatedIds : [],
        );

        $em->flush();

        $action = $isNew ? 'creó noticia' : 'editó noticia';
        $this->activityLogger->log($em, $user, $action, 'article', $article->getId(), $article->getTitle());
        $em->flush();
        $this->articleCache->invalidateSite($site);

        $queued = $this->imageEnhancementDispatcher->dispatchForArticle($site, $article);
        if ($queued > 0) {
            $this->addFlash(
                'info',
                sprintf('Se están mejorando %d imagen(es) en segundo plano para cumplir la calidad editorial.', $queued),
            );
        }

        $this->addFlash('success', $isNew ? 'Artículo creado.' : 'Artículo guardado.');

        return $this->redirectToRoute('admin_articles_index');
    }

    private function loadPolls(EntityManagerInterface $em): array
    {
        $repo = $em->getRepository(Poll::class);
        if ($repo instanceof PollRepository) {
            return $repo->findAllOrdered();
        }

        return $repo->findBy([], ['createdAt' => 'DESC']);
    }

    private function loadGalleries(EntityManagerInterface $em): array
    {
        $repo = $em->getRepository(Gallery::class);
        if ($repo instanceof GalleryRepository) {
            return $repo->findAllOrdered();
        }

        return $repo->findBy([], ['updatedAt' => 'DESC']);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function loadAuthorChoices(SiteContext $site, ?User $currentUser = null): array
    {
        $seen = [];
        $authors = [];

        $add = static function (User $staffUser) use (&$seen, &$authors): void {
            $id = $staffUser->getId();
            if ($id === null || isset($seen[$id])) {
                return;
            }
            $seen[$id] = true;
            $authors[] = [
                'id' => $id,
                'name' => $staffUser->getName(),
            ];
        };

        if ($currentUser !== null) {
            $add($currentUser);
        }

        $platformSite = $this->platformEm->find(Site::class, $site->id);
        if ($platformSite !== null) {
            foreach ($this->userSiteRoleQuery->findStaffForSite($platformSite) as $assignment) {
                $add($assignment->getUser());
            }
        }

        usort($authors, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $authors;
    }

    private function applyAuthorFromRequest(Request $request, SiteContext $site, Article $article): void
    {
        $authorUserId = (int) $request->request->get('authorUserId', 0);
        $authorName = trim((string) $request->request->get('authorName', ''));

        if ($authorUserId > 0) {
            $authorUser = $this->findAssignableAuthorUser($site, $authorUserId);
            if ($authorUser !== null) {
                $article->setAuthorUserId($authorUser->getId());
                if ($authorName === '') {
                    $authorName = $authorUser->getName();
                }
                $article->setAuthorName($authorName);

                return;
            }
        }

        $article->setAuthorUserId(null);
        $article->setAuthorName($authorName !== '' ? $authorName : null);
    }

    private function findAssignableAuthorUser(SiteContext $site, int $userId): ?User
    {
        $user = $this->platformEm->find(User::class, $userId);
        if (!$user instanceof User) {
            return null;
        }

        if ($user->isPlatformSuperadmin()) {
            return $user;
        }

        $platformSite = $this->platformEm->find(Site::class, $site->id);
        if ($platformSite === null) {
            return null;
        }

        $assignment = $this->platformEm->getRepository(UserSiteRole::class)->findOneBy([
            'site' => $platformSite,
            'user' => $user,
        ]);
        if (!$assignment instanceof UserSiteRole || !SiteRole::isStaff($assignment->getRole())) {
            return null;
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $errors
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $extra
     */
    private function renderArticleForm(
        EntityManagerInterface $em,
        SiteContext $site,
        ?Article $article,
        array $errors,
        array $formData,
        array $extra = [],
    ): Response {
        $currentUser = $this->getUser();

        return $this->render('admin/article/form.html.twig', array_merge([
            'site' => $site,
            'article' => $article,
            'categories' => $this->loadCategories($em),
            'polls' => $this->loadPolls($em),
            'galleries' => $this->loadGalleries($em),
            'authors' => $this->loadAuthorChoices($site, $currentUser instanceof User ? $currentUser : null),
            'errors' => $errors,
            'formData' => $formData,
        ], $this->articleFormExtras($site), $extra));
    }

    /** @return array{aiConfigured: bool} */
    private function articleFormExtras(SiteContext $site): array
    {
        return [
            'aiConfigured' => $this->settingsReader->getAiConfig($site)->isConfigured(),
        ];
    }

    private function isBodyHtmlEmpty(string $bodyHtml): bool
    {
        $text = trim(html_entity_decode(strip_tags($bodyHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '';
    }

    private function syncArticlePoll(Request $request, EntityManagerInterface $em, Article $article): void
    {
        if (!$request->request->has('includePoll')) {
            $article->setPoll(null);

            return;
        }

        $pollSource = (string) $request->request->get('pollSource', 'existing');
        $isOpen = $request->request->has('pollIsOpen');
        $showResults = $request->request->has('pollShowResultsBeforeVote');
        $closesAtRaw = trim((string) $request->request->get('pollClosesAt', ''));
        $closesAt = null;
        if ($closesAtRaw !== '') {
            try {
                $closesAt = new \DateTimeImmutable($closesAtRaw);
            } catch (\Throwable) {
                $closesAt = null;
            }
        }

        if ($pollSource === 'existing') {
            $pollId = (int) $request->request->get('pollId', 0);
            $article->setPoll($pollId > 0 ? $em->find(Poll::class, $pollId) : null);

            return;
        }

        $question = trim((string) $request->request->get('pollQuestion', ''));
        $options = $this->pollSync->labelsFromRequest($request->request->all('pollOptions'));
        if ($question === '') {
            return;
        }

        try {
            $attached = $article->getPoll();
            if ($attached !== null && $pollSource === 'edit') {
                $this->pollSync->syncPoll($attached, $question, $options, $isOpen, $showResults, $closesAt, $em);
            } else {
                $poll = $this->pollSync->createPoll($question, $options, $isOpen, $showResults, $closesAt, $em);
                $article->setPoll($poll);
            }
        } catch (\InvalidArgumentException) {
            // Ignorar encuesta inválida sin bloquear el artículo.
        }
    }

    private function syncArticleGallery(Request $request, EntityManagerInterface $em, Article $article): void
    {
        if (!$request->request->has('includeGallery')) {
            $article->setGallery(null);

            return;
        }

        $gallerySource = (string) $request->request->get('gallerySource', 'existing');
        $imageRows = $request->request->all('galleryImages');
        if (!\is_array($imageRows)) {
            $imageRows = [];
        }

        if ($gallerySource === 'existing') {
            $galleryId = (int) $request->request->get('galleryId', 0);
            $article->setGallery($galleryId > 0 ? $em->find(Gallery::class, $galleryId) : null);

            return;
        }

        $title = trim((string) $request->request->get('galleryTitle', ''));
        if ($title === '') {
            $title = 'Galería: '.$article->getTitle();
        }

        try {
            $attached = $article->getGallery();
            if ($attached !== null && $gallerySource === 'edit') {
                $this->gallerySync->syncGallery($attached, $title, $imageRows, $em);
            } else {
                $gallery = $this->gallerySync->createGallery($title, $imageRows, $em);
                $article->setGallery($gallery);
            }
        } catch (\InvalidArgumentException) {
            // Ignorar galería inválida sin bloquear el artículo.
        }
    }

    private function syncArticleVideo(Request $request, EntityManagerInterface $em, Article $article): void
    {
        if (!$request->request->has('includeVideo')) {
            $article->setVideo(null)->setVideoReplacesFeatured(false);

            return;
        }

        $videoMediaId = (int) $request->request->get('videoMediaId', 0);
        if ($videoMediaId <= 0) {
            return;
        }

        $media = $em->find(Media::class, $videoMediaId);
        if ($media === null || !$media->isVideo()) {
            return;
        }

        $article->setVideo($media);
        $article->setVideoReplacesFeatured($request->request->has('videoReplacesFeatured'));
    }

    /**
     * @return array{suggestedTags: list<string>, extraTagsValue: string, selectedRelated: list<Article>}
     */
    private function articleFormExtraContext(
        EntityManagerInterface $em,
        ?Article $article,
        Request $request,
        string $title,
        string $bodyHtml,
        string $slug,
        ?string $subtitle,
        ?string $lead,
        ?Category $category,
    ): array {
        $preview = $article ?? new Article($title, $bodyHtml, $slug !== '' ? $slug : 'preview');
        $preview->setSubtitle($subtitle)->setLead($lead)->setCategory($category);

        return [
            'suggestedTags' => $this->tagSyncService->suggestForArticle($preview),
            'extraTagsValue' => (string) $request->request->get('extraTags', ''),
            'selectedRelated' => $this->selectedRelatedFromRequest($em, $request),
        ];
    }

    /**
     * @return list<Article>
     */
    private function selectedRelatedFromRequest(EntityManagerInterface $em, Request $request): array
    {
        $raw = $request->request->all('relatedIds');
        if (!\is_array($raw) || $raw === []) {
            return [];
        }

        $repo = $em->getRepository(Article::class);
        if (!$repo instanceof ArticleRepository) {
            return [];
        }

        return $repo->findByIdsPreservingOrder($raw);
    }

    private function findArticleOr404(EntityManagerInterface $em, int $id): Article
    {
        $article = $em->find(Article::class, $id);
        if ($article === null) {
            throw $this->createNotFoundException('Artículo no encontrado.');
        }

        return $article;
    }

    private function parseAgendaDate(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('Europe/Madrid'));
        } catch (\Throwable) {
            return null;
        }
    }
}
