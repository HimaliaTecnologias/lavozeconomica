<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Article;
use App\Entity\Site\ArticleImage;
use App\Entity\Site\Media;
use App\Editorial\MediaEnhancementDispatcher;
use App\Editorial\MediaImageTransformer;
use App\Editorial\MediaJsonSerializer;
use App\Editorial\MediaLibraryImportService;
use App\Editorial\MediaLinkedArticleSyncService;
use App\Editorial\MediaProcessingDispatcher;
use App\Editorial\MediaStoragePathResolver;
use App\Editorial\MediaUploadService;
use App\Repository\Site\MediaRepository;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/media')]
#[IsGranted('ROLE_USER')]
final class MediaController extends AbstractAdminController
{
    private const PER_PAGE = 24;

    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly MediaUploadService $uploadService,
        private readonly MediaEnhancementDispatcher $enhancementDispatcher,
        private readonly MediaProcessingDispatcher $processingDispatcher,
        private readonly MediaLinkedArticleSyncService $linkedArticleSync,
        private readonly MediaJsonSerializer $jsonSerializer,
        private readonly MediaStoragePathResolver $pathResolver,
        private readonly MediaLibraryImportService $libraryImport,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_media_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::MEDIA);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $this->mediaRepository($em);

        $unlinkedImages = $this->libraryImport->countUnlinkedArticleImages($em);
        if ($unlinkedImages > 0) {
            $importResult = $this->libraryImport->importFromArticleImages($em);
            if ($importResult['imported'] > 0) {
                $this->addFlash(
                    'success',
                    sprintf(
                        'Se importaron %d imagen(es) desde las noticias publicadas a la biblioteca.',
                        $importResult['imported'],
                    ),
                );
            }
        }

        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $picker = $request->query->getBoolean('picker');

        $result = $repo->searchPaginated($query, $page, self::PER_PAGE, $this->mediaKindFromRequest($request));
        $totalPages = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        return $this->render('admin/media/index.html.twig', [
            'site' => $site,
            'mediaItems' => $result['items'],
            'query' => $query,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $result['total'],
            'picker' => $picker,
            'unlinkedImages' => $this->libraryImport->countUnlinkedArticleImages($em),
        ]);
    }

    #[Route('/import-from-articles', name: 'admin_media_import_from_articles', methods: ['POST'])]
    public function importFromArticles(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::MEDIA);
        $em = $this->siteEmFactory->forContext($site);

        if (!$this->isCsrfTokenValid('media_import_articles', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $result = $this->libraryImport->importFromArticleImages($em);
        if ($result['linked'] === 0 && $result['imported'] === 0) {
            $this->addFlash('info', 'No hay imágenes pendientes de importar desde las noticias.');
        } else {
            $this->addFlash(
                'success',
                sprintf(
                    'Biblioteca actualizada: %d nueva(s), %d vinculada(s), %d omitida(s).',
                    $result['imported'],
                    $result['linked'],
                    $result['skipped'],
                ),
            );
        }

        return $this->redirectToRoute('admin_media_index');
    }

    #[Route('/api', name: 'admin_media_api', methods: ['GET'])]
    public function api(Request $request): JsonResponse
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::MEDIA);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $this->mediaRepository($em);

        $query = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));

        $result = $repo->searchPaginated($query, $page, self::PER_PAGE, $this->mediaKindFromRequest($request));
        $totalPages = max(1, (int) ceil($result['total'] / self::PER_PAGE));

        return new JsonResponse([
            'items' => $this->jsonSerializer->toArrayList($result['items']),
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'total' => $result['total'],
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/upload', name: 'admin_media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        [$site, $user] = $this->requirePermission(\App\Security\SitePermission::MEDIA);

        if (!$this->isCsrfTokenValid('media_upload', (string) $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token CSRF inválido.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');
        if (!$file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(['error' => 'No se recibió ningún archivo.'], Response::HTTP_BAD_REQUEST);
        }

        $em = $this->siteEmFactory->forContext($site);

        try {
            $media = $this->uploadService->upload($site, $file, $em, $user->getId());
            $this->enhancementDispatcher->dispatchIfNeeded($site, $media);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'Error al subir: '.$e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'media' => $this->jsonSerializer->toArray($media),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_media_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::MEDIA);
        $em = $this->siteEmFactory->forContext($site);

        if (!$this->isCsrfTokenValid('delete_media'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $media = $em->find(Media::class, $id);
        if ($media === null) {
            throw $this->createNotFoundException('Medio no encontrado.');
        }

        $inUse = $em->getRepository(ArticleImage::class)->count(['media' => $media]);
        $videoUse = $em->getRepository(Article::class)->count(['video' => $media]);
        if ($inUse > 0 || $videoUse > 0) {
            $this->addFlash('error', 'No se puede eliminar: el archivo está en uso en '
                .($inUse + $videoUse).' noticia(s).');

            return $this->redirectToRoute('admin_media_index');
        }

        $absolutePath = $this->pathResolver->absolutePathFromPublic($media->getPublicPath());
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        $em->remove($media);
        $em->flush();

        $this->addFlash('success', 'Archivo eliminado de la biblioteca.');

        return $this->redirectToRoute('admin_media_index');
    }

    #[Route('/{id}', name: 'admin_media_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function show(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::MEDIA);
        $em = $this->siteEmFactory->forContext($site);
        $media = $this->findMediaOr404($em, $id);
        $linkedCount = $em->getRepository(ArticleImage::class)->count(['media' => $media]);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('media_edit'.$id, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $media->setTitle(trim((string) $request->request->get('title', '')) ?: null);
            $media->setAlt(trim((string) $request->request->get('alt', '')) ?: null);
            $media->setCaption(trim((string) $request->request->get('caption', '')) ?: null);
            $em->flush();

            $this->linkedArticleSync->syncFromMedia($media, $em);
            $em->flush();

            $this->addFlash('success', 'Metadatos guardados.');

            return $this->redirectToRoute('admin_media_show', ['id' => $id]);
        }

        return $this->render('admin/media/show.html.twig', [
            'site' => $site,
            'media' => $media,
            'linkedCount' => $linkedCount,
            'transformOperations' => $this->filterOperations('transform'),
            'anonymizeOperations' => $this->filterOperations('anonymize'),
        ]);
    }

    #[Route('/{id}/process', name: 'admin_media_process', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function process(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::MEDIA);
        $em = $this->siteEmFactory->forContext($site);
        $media = $this->findMediaOr404($em, $id);

        if (!$this->isCsrfTokenValid('media_process'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $operation = (string) $request->request->get('operation', '');
        $allowed = array_merge(['enhance', 'enhance_force'], array_keys(MediaImageTransformer::operationLabels()));
        if (!in_array($operation, $allowed, true)) {
            $this->addFlash('error', 'Operación no válida.');

            return $this->redirectToRoute('admin_media_show', ['id' => $id]);
        }

        $this->processingDispatcher->dispatch($site, $media, $operation);
        $this->addFlash('success', 'Procesamiento en curso. Recarga en unos segundos para ver el resultado.');

        return $this->redirectToRoute('admin_media_show', ['id' => $id]);
    }

    private function findMediaOr404(EntityManagerInterface $em, int $id): Media
    {
        $media = $em->find(Media::class, $id);
        if ($media === null) {
            throw $this->createNotFoundException('Medio no encontrado.');
        }

        return $media;
    }

    /** @return array<string, string> */
    private function filterOperations(string $type): array
    {
        $all = MediaImageTransformer::operationLabels();
        $filtered = [];
        foreach ($all as $op => $label) {
            $isAnonymize = str_starts_with($op, 'anonymize_');
            if ($type === 'anonymize' && $isAnonymize) {
                $filtered[$op] = $label;
            }
            if ($type === 'transform' && !$isAnonymize) {
                $filtered[$op] = $label;
            }
        }

        return $filtered;
    }

    private function mediaRepository(EntityManagerInterface $em): MediaRepository
    {
        $repo = $em->getRepository(Media::class);
        if (!$repo instanceof MediaRepository) {
            throw new \RuntimeException('MediaRepository no disponible.');
        }

        return $repo;
    }

    private function mediaKindFromRequest(Request $request): ?string
    {
        $kind = (string) $request->query->get('kind', '');
        if ($kind === 'image' || $kind === 'video') {
            return $kind;
        }

        return null;
    }
}
