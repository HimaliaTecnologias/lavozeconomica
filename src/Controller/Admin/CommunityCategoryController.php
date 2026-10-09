<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\ClassifiedCategory;
use App\Entity\Site\ForumCategory;
use App\Editorial\SlugGenerator;
use App\Repository\Site\ClassifiedCategoryRepository;
use App\Repository\Site\ForumCategoryRepository;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/comunidad')]
#[IsGranted('ROLE_USER')]
final class CommunityCategoryController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly SlugGenerator $slugGenerator,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('/foro/categorias', name: 'admin_community_forum_categories', methods: ['GET'])]
    public function forumCategories(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $this->forumCategoryRepository($em);

        return $this->render('admin/community/forum_categories.html.twig', [
            'site' => $site,
            'categories' => $repo->findAllOrdered(),
        ]);
    }

    #[Route('/foro/categorias/nueva', name: 'admin_community_forum_category_new', methods: ['GET', 'POST'])]
    public function forumCategoryNew(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);

        if ($request->isMethod('POST')) {
            return $this->handleForumCategorySave($request, $em, $site, null);
        }

        return $this->render('admin/community/forum_category_form.html.twig', [
            'site' => $site,
            'category' => null,
            'errors' => [],
            'formData' => [],
        ]);
    }

    #[Route('/foro/categorias/{id}/editar', name: 'admin_community_forum_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function forumCategoryEdit(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $category = $this->findForumCategoryOr404($em, $id);

        if ($request->isMethod('POST')) {
            return $this->handleForumCategorySave($request, $em, $site, $category);
        }

        return $this->render('admin/community/forum_category_form.html.twig', [
            'site' => $site,
            'category' => $category,
            'errors' => [],
            'formData' => [],
        ]);
    }

    #[Route('/foro/categorias/{id}/eliminar', name: 'admin_community_forum_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function forumCategoryDelete(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $category = $this->findForumCategoryOr404($em, $id);

        if (!$this->isCsrfTokenValid('delete_forum_category'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $topicCount = (int) $em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(\App\Entity\Site\ForumTopic::class, 't')
            ->andWhere('t.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();

        if ($topicCount > 0) {
            $this->addFlash('error', 'No se puede eliminar: hay temas en esta categoría.');

            return $this->redirectToRoute('admin_community_forum_categories');
        }

        $em->remove($category);
        $em->flush();
        $this->addFlash('success', 'Categoría del foro eliminada.');

        return $this->redirectToRoute('admin_community_forum_categories');
    }

    #[Route('/avisos/categorias', name: 'admin_community_classified_categories', methods: ['GET'])]
    public function classifiedCategories(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $this->classifiedCategoryRepository($em);

        return $this->render('admin/community/classified_categories.html.twig', [
            'site' => $site,
            'categories' => $repo->findAllOrdered(),
        ]);
    }

    #[Route('/avisos/categorias/nueva', name: 'admin_community_classified_category_new', methods: ['GET', 'POST'])]
    public function classifiedCategoryNew(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);

        if ($request->isMethod('POST')) {
            return $this->handleClassifiedCategorySave($request, $em, $site, null);
        }

        return $this->render('admin/community/classified_category_form.html.twig', [
            'site' => $site,
            'category' => null,
            'errors' => [],
            'formData' => [],
        ]);
    }

    #[Route('/avisos/categorias/{id}/editar', name: 'admin_community_classified_category_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function classifiedCategoryEdit(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $category = $this->findClassifiedCategoryOr404($em, $id);

        if ($request->isMethod('POST')) {
            return $this->handleClassifiedCategorySave($request, $em, $site, $category);
        }

        return $this->render('admin/community/classified_category_form.html.twig', [
            'site' => $site,
            'category' => $category,
            'errors' => [],
            'formData' => [],
        ]);
    }

    #[Route('/avisos/categorias/{id}/eliminar', name: 'admin_community_classified_category_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function classifiedCategoryDelete(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::COMMUNITY);
        $em = $this->siteEmFactory->forContext($site);
        $category = $this->findClassifiedCategoryOr404($em, $id);

        if (!$this->isCsrfTokenValid('delete_classified_category'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $adCount = (int) $em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(\App\Entity\Site\ClassifiedAd::class, 'a')
            ->andWhere('a.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();

        if ($adCount > 0) {
            $this->addFlash('error', 'No se puede eliminar: hay anuncios en esta categoría.');

            return $this->redirectToRoute('admin_community_classified_categories');
        }

        $em->remove($category);
        $em->flush();
        $this->addFlash('success', 'Categoría de avisos eliminada.');

        return $this->redirectToRoute('admin_community_classified_categories');
    }

    private function handleForumCategorySave(
        Request $request,
        EntityManagerInterface $em,
        \App\Core\Site\SiteContext $site,
        ?ForumCategory $category,
    ): Response {
        if (!$this->isCsrfTokenValid('forum_category_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $repo = $this->forumCategoryRepository($em);
        $name = trim((string) $request->request->get('name', ''));
        $slug = trim((string) $request->request->get('slug', ''));
        if ($slug === '' && $name !== '') {
            $slug = $this->slugGenerator->fromTitle($name);
        }
        $description = trim((string) $request->request->get('description', '')) ?: null;
        $sortOrder = (int) $request->request->get('sortOrder', 0);
        $isActive = $request->request->has('isActive');

        $errors = $this->validateCategoryFields($repo, $name, $slug, $category?->getId());

        if ($errors === []) {
            if ($category === null) {
                $category = new ForumCategory($name, $slug);
                $em->persist($category);
            } else {
                $category->setName($name)->setSlug($slug);
            }

            $category
                ->setDescription($description)
                ->setSortOrder($sortOrder)
                ->setActive($isActive);

            $em->flush();
            $this->addFlash('success', 'Categoría del foro guardada.');

            return $this->redirectToRoute('admin_community_forum_categories');
        }

        return $this->render('admin/community/forum_category_form.html.twig', [
            'site' => $site,
            'category' => $category,
            'errors' => $errors,
            'formData' => $request->request->all(),
        ]);
    }

    private function handleClassifiedCategorySave(
        Request $request,
        EntityManagerInterface $em,
        \App\Core\Site\SiteContext $site,
        ?ClassifiedCategory $category,
    ): Response {
        if (!$this->isCsrfTokenValid('classified_category_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $repo = $this->classifiedCategoryRepository($em);
        $name = trim((string) $request->request->get('name', ''));
        $slug = trim((string) $request->request->get('slug', ''));
        if ($slug === '' && $name !== '') {
            $slug = $this->slugGenerator->fromTitle($name);
        }
        $description = trim((string) $request->request->get('description', '')) ?: null;
        $sortOrder = (int) $request->request->get('sortOrder', 0);
        $isActive = $request->request->has('isActive');

        $errors = $this->validateCategoryFields($repo, $name, $slug, $category?->getId());

        if ($errors === []) {
            if ($category === null) {
                $category = new ClassifiedCategory($name, $slug);
                $em->persist($category);
            } else {
                $category->setName($name)->setSlug($slug);
            }

            $category
                ->setDescription($description)
                ->setSortOrder($sortOrder)
                ->setActive($isActive);

            $em->flush();
            $this->addFlash('success', 'Categoría de avisos guardada.');

            return $this->redirectToRoute('admin_community_classified_categories');
        }

        return $this->render('admin/community/classified_category_form.html.twig', [
            'site' => $site,
            'category' => $category,
            'errors' => $errors,
            'formData' => $request->request->all(),
        ]);
    }

    /**
     * @param ForumCategoryRepository|ClassifiedCategoryRepository $repo
     *
     * @return array<string, string>
     */
    private function validateCategoryFields(
        ForumCategoryRepository|ClassifiedCategoryRepository $repo,
        string $name,
        string $slug,
        ?int $excludeId,
    ): array {
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }
        if ($slug !== '') {
            $existing = $repo->findOneBy(['slug' => $slug]);
            if ($existing !== null && $existing->getId() !== $excludeId) {
                $errors['slug'] = 'Ese slug ya está en uso.';
            }
        }

        return $errors;
    }

    private function findForumCategoryOr404(EntityManagerInterface $em, int $id): ForumCategory
    {
        $category = $em->find(ForumCategory::class, $id);
        if ($category === null) {
            throw $this->createNotFoundException('Categoría no encontrada.');
        }

        return $category;
    }

    private function findClassifiedCategoryOr404(EntityManagerInterface $em, int $id): ClassifiedCategory
    {
        $category = $em->find(ClassifiedCategory::class, $id);
        if ($category === null) {
            throw $this->createNotFoundException('Categoría no encontrada.');
        }

        return $category;
    }

    private function forumCategoryRepository(EntityManagerInterface $em): ForumCategoryRepository
    {
        $repo = $em->getRepository(ForumCategory::class);
        if (!$repo instanceof ForumCategoryRepository) {
            throw new \RuntimeException('ForumCategoryRepository no disponible.');
        }

        return $repo;
    }

    private function classifiedCategoryRepository(EntityManagerInterface $em): ClassifiedCategoryRepository
    {
        $repo = $em->getRepository(ClassifiedCategory::class);
        if (!$repo instanceof ClassifiedCategoryRepository) {
            throw new \RuntimeException('ClassifiedCategoryRepository no disponible.');
        }

        return $repo;
    }
}
