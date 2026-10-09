<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Category;
use App\Editorial\SlugGenerator;
use App\Repository\Site\CategoryRepository;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/categories')]
#[IsGranted('ROLE_USER')]
final class CategoryController extends AbstractAdminController
{
    private const MAX_DEPTH = 2;

    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly SlugGenerator $slugGenerator,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_categories_index', methods: ['GET'])]
    public function index(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::CATEGORIES);
        $repo = $this->categoryRepository($this->siteEmFactory->forContext($site));
        $categoryTree = $repo->findRootsForAdmin();
        $articleCounts = $repo->countPublishedArticlesByCategory();

        return $this->render('admin/category/index.html.twig', [
            'site' => $site,
            'categoryTree' => $categoryTree,
            'articleCounts' => $articleCounts,
            'displayCounts' => $this->buildDisplayCounts($categoryTree, $articleCounts),
            'totalCategories' => count($repo->findAll()),
            'publishedTotal' => array_sum($articleCounts),
        ]);
    }

    /**
     * @param list<Category>   $roots
     * @param array<int, int>  $articleCounts
     *
     * @return array<int, int>
     */
    private function buildDisplayCounts(array $roots, array $articleCounts): array
    {
        $display = [];

        $subtreeTotal = function (Category $category) use (&$subtreeTotal, $articleCounts): int {
            $id = $category->getId();
            $total = $id !== null ? ($articleCounts[$id] ?? 0) : 0;
            foreach ($category->getChildren() as $child) {
                $total += $subtreeTotal($child);
            }

            return $total;
        };

        foreach ($roots as $root) {
            $rootId = $root->getId();
            if ($rootId !== null) {
                $display[$rootId] = $subtreeTotal($root);
            }
            foreach ($root->getChildren() as $child) {
                $childId = $child->getId();
                if ($childId !== null) {
                    $display[$childId] = $subtreeTotal($child);
                }
                foreach ($child->getChildren() as $grand) {
                    $grandId = $grand->getId();
                    if ($grandId !== null) {
                        $display[$grandId] = $articleCounts[$grandId] ?? 0;
                    }
                }
            }
        }

        return $display;
    }

    #[Route('/new', name: 'admin_categories_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::CATEGORIES);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $this->categoryRepository($em);

        $parentId = $request->query->getInt('parent');
        $defaultParent = $parentId > 0 ? $em->find(Category::class, $parentId) : null;

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $em, $site, $repo, null);
        }

        return $this->render('admin/category/form.html.twig', [
            'site' => $site,
            'category' => null,
            'parentOptions' => $repo->findParentOptions(),
            'defaultParentId' => $defaultParent?->getId(),
            'errors' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_categories_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::CATEGORIES);
        $em = $this->siteEmFactory->forContext($site);
        $repo = $this->categoryRepository($em);
        $category = $this->findCategoryOr404($em, $id);

        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $em, $site, $repo, $category);
        }

        return $this->render('admin/category/form.html.twig', [
            'site' => $site,
            'category' => $category,
            'parentOptions' => $repo->findParentOptions($category),
            'defaultParentId' => $category->getParent()?->getId(),
            'errors' => [],
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_categories_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::CATEGORIES);
        $em = $this->siteEmFactory->forContext($site);
        $category = $this->findCategoryOr404($em, $id);

        if (!$this->isCsrfTokenValid('delete_category'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        if ($category->getChildren()->count() > 0) {
            $this->addFlash('error', 'No se puede eliminar: tiene subcategorías. Elimínalas o reasígnalas primero.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $em->remove($category);
        $em->flush();

        $this->addFlash('success', 'Categoría eliminada.');

        return $this->redirectToRoute('admin_categories_index');
    }

    #[Route('/{id}/move/{direction}', name: 'admin_categories_move', methods: ['POST'], requirements: [
        'id' => '\d+',
        'direction' => 'up|down',
    ])]
    public function move(Request $request, int $id, string $direction): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::CATEGORIES);
        $em = $this->siteEmFactory->forContext($site);
        $category = $this->findCategoryOr404($em, $id);

        if (!$this->isCsrfTokenValid('move_category'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $siblings = $this->siblingsOrdered($em, $category);
        $index = null;
        foreach ($siblings as $i => $sibling) {
            if ($sibling->getId() === $category->getId()) {
                $index = $i;
                break;
            }
        }

        if ($index === null) {
            $this->addFlash('error', 'No se pudo reordenar la categoría.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= \count($siblings)) {
            $this->addFlash('error', $direction === 'up'
                ? 'La categoría ya está en la primera posición de su nivel.'
                : 'La categoría ya está en la última posición de su nivel.');

            return $this->redirectToRoute('admin_categories_index');
        }

        $tmp = $siblings[$index];
        $siblings[$index] = $siblings[$swapWith];
        $siblings[$swapWith] = $tmp;

        foreach ($siblings as $i => $sibling) {
            $sibling->setSortOrder(($i + 1) * 10);
        }
        $em->flush();

        $this->addFlash('success', 'Orden del menú actualizado.');

        return $this->redirectToRoute('admin_categories_index');
    }

    /** @return list<Category> */
    private function siblingsOrdered(EntityManagerInterface $em, Category $category): array
    {
        $parent = $category->getParent();
        if ($parent === null) {
            return $this->categoryRepository($em)->findBy(
                ['parent' => null],
                ['sortOrder' => 'ASC', 'name' => 'ASC'],
            );
        }

        $siblings = $parent->getChildren()->toArray();
        usort(
            $siblings,
            static fn (Category $a, Category $b): int => ($a->getSortOrder() <=> $b->getSortOrder())
                ?: strcasecmp($a->getName(), $b->getName()),
        );

        return array_values($siblings);
    }

    private function handleSave(
        Request $request,
        EntityManagerInterface $em,
        SiteContext $site,
        CategoryRepository $repo,
        ?Category $category,
    ): Response {
        if (!$this->isCsrfTokenValid('category_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $name = trim((string) $request->request->get('name', ''));
        $slug = trim((string) $request->request->get('slug', ''));
        $description = trim((string) $request->request->get('description', '')) ?: null;
        $sortOrder = (int) $request->request->get('sortOrder', 0);
        $showInNav = $request->request->has('showInNav');
        $parentId = trim((string) $request->request->get('parentId', ''));

        $errors = [];
        if ($name === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }
        if ($slug === '' && $name !== '') {
            $slug = $this->slugGenerator->fromTitle($name);
        }
        if ($slug !== '' && $repo->isSlugTaken($slug, $category?->getId())) {
            $errors['slug'] = 'Ese slug ya está en uso.';
        }

        $parent = null;
        if ($parentId !== '') {
            $parent = $em->find(Category::class, (int) $parentId);
            if ($parent === null) {
                $errors['parentId'] = 'Categoría padre no encontrada.';
            } elseif ($category !== null && ($parent->getId() === $category->getId() || $parent->isDescendantOf($category))) {
                $errors['parentId'] = 'No puede asignarse como padre una subcategoría propia.';
            } elseif ($parent->getDepth() >= self::MAX_DEPTH) {
                $errors['parentId'] = 'La jerarquía máxima permitida es de '.(self::MAX_DEPTH + 1).' niveles.';
            }
        }

        if ($errors === []) {
            if ($category === null) {
                $category = new Category($name, $slug);
                $em->persist($category);
            } else {
                $category->setName($name)->setSlug($slug);
            }

            $category
                ->setDescription($description)
                ->setSortOrder($sortOrder)
                ->setShowInNav($showInNav)
                ->setParent($parent);

            $em->flush();

            $this->addFlash('success', 'Categoría guardada.');

            return $this->redirectToRoute('admin_categories_index');
        }

        return $this->render('admin/category/form.html.twig', [
            'site' => $site,
            'category' => $category,
            'parentOptions' => $repo->findParentOptions($category),
            'defaultParentId' => $parentId !== '' ? (int) $parentId : null,
            'errors' => $errors,
            'formData' => $request->request->all(),
        ]);
    }

    private function findCategoryOr404(EntityManagerInterface $em, int $id): Category
    {
        $category = $em->find(Category::class, $id);
        if ($category === null) {
            throw $this->createNotFoundException('Categoría no encontrada.');
        }

        return $category;
    }

    private function categoryRepository(EntityManagerInterface $em): CategoryRepository
    {
        $repo = $em->getRepository(Category::class);
        if (!$repo instanceof CategoryRepository) {
            throw new \RuntimeException('CategoryRepository no disponible.');
        }

        return $repo;
    }
}
