<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Gallery;
use App\Editorial\GallerySyncService;
use App\Repository\Site\GalleryRepository;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/galleries')]
#[IsGranted('ROLE_USER')]
final class GalleryController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly GallerySyncService $gallerySync,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_galleries_index', methods: ['GET'])]
    public function index(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::GALLERIES);
        $repo = $this->galleryRepository($this->siteEmFactory->forContext($site));

        return $this->render('admin/gallery/index.html.twig', [
            'site' => $site,
            'galleries' => $repo->findAllOrdered(),
            'articleCounts' => $repo->countArticlesByGallery(),
        ]);
    }

    #[Route('/new', name: 'admin_galleries_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::GALLERIES);
        $em = $this->siteEmFactory->forContext($site);

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $em, $site, null);
        }

        return $this->render('admin/gallery/form.html.twig', [
            'site' => $site,
            'gallery' => null,
            'errors' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_galleries_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::GALLERIES);
        $em = $this->siteEmFactory->forContext($site);
        $gallery = $this->findGalleryOr404($em, $id);

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $em, $site, $gallery);
        }

        return $this->render('admin/gallery/form.html.twig', [
            'site' => $site,
            'gallery' => $gallery,
            'errors' => [],
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_galleries_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::GALLERIES);
        $em = $this->siteEmFactory->forContext($site);
        $gallery = $this->findGalleryOr404($em, $id);

        if (!$this->isCsrfTokenValid('delete_gallery'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $em->remove($gallery);
        $em->flush();

        $this->addFlash('success', 'Galería eliminada.');

        return $this->redirectToRoute('admin_galleries_index');
    }

    private function handleSave(Request $request, EntityManagerInterface $em, $site, ?Gallery $gallery): Response
    {
        if (!$this->isCsrfTokenValid('gallery_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $title = trim((string) $request->request->get('title', ''));
        $imageRows = $request->request->all('images');
        if (!\is_array($imageRows)) {
            $imageRows = [];
        }

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'El título es obligatorio.';
        }

        if ($errors === []) {
            try {
                if ($gallery === null) {
                    $this->gallerySync->createGallery($title, $imageRows, $em);
                } else {
                    $this->gallerySync->syncGallery($gallery, $title, $imageRows, $em);
                }
                $em->flush();

                $this->addFlash('success', 'Galería guardada.');

                return $this->redirectToRoute('admin_galleries_index');
            } catch (\InvalidArgumentException $e) {
                $errors['images'] = $e->getMessage();
            }
        }

        return $this->render('admin/gallery/form.html.twig', [
            'site' => $site,
            'gallery' => $gallery,
            'errors' => $errors,
            'formData' => $request->request->all(),
        ]);
    }

    private function findGalleryOr404(EntityManagerInterface $em, int $id): Gallery
    {
        $gallery = $em->find(Gallery::class, $id);
        if ($gallery === null) {
            throw $this->createNotFoundException('Galería no encontrada.');
        }

        return $gallery;
    }

    private function galleryRepository(EntityManagerInterface $em): GalleryRepository
    {
        $repo = $em->getRepository(Gallery::class);
        if (!$repo instanceof GalleryRepository) {
            throw new \RuntimeException('GalleryRepository no disponible.');
        }

        return $repo;
    }
}
