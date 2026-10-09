<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Platform\User;
use App\Entity\Site\ClassifiedAd;
use App\Entity\Site\ClassifiedCategory;
use App\Editorial\CommunityRichTextSanitizer;
use App\Editorial\CommunitySlugAllocator;
use App\Editorial\PublicRelatedNewsProvider;
use App\Repository\Site\ClassifiedAdRepository;
use App\Repository\Site\ClassifiedCategoryRepository;
use App\Security\AbuseRateLimiter;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class PublicClassifiedsController extends AbstractController
{
    #[Route('/avisos', name: 'public_classifieds', methods: ['GET'])]
    public function index(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        PublicRelatedNewsProvider $relatedNewsProvider,
    ): Response {
        return $this->renderClassifiedsIndex(
            $holder,
            $pageContextFactory,
            $settingsReader,
            $siteEmFactory,
            $relatedNewsProvider,
            null,
            'Avisos y compra-venta — ',
            'Anuncios clasificados publicados por los lectores de ',
        );
    }

    #[Route('/avisos/categoria/{categorySlug}', name: 'public_classifieds_category', methods: ['GET'])]
    public function category(
        string $categorySlug,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        PublicRelatedNewsProvider $relatedNewsProvider,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getClassifiedsConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('La sección de avisos no está disponible.');
        }

        $em = $siteEmFactory->forContext($site);
        $category = $this->classifiedCategoryRepository($em)->findActiveBySlug($categorySlug);
        if ($category === null) {
            throw $this->createNotFoundException('Categoría no encontrada.');
        }

        return $this->renderClassifiedsIndex(
            $holder,
            $pageContextFactory,
            $settingsReader,
            $siteEmFactory,
            $relatedNewsProvider,
            $category,
            $category->getName().' — Avisos — ',
            $category->getDescription() ?? 'Anuncios de '.$category->getName().' en ',
        );
    }

    #[Route('/avisos/nuevo', name: 'public_classifieds_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function create(
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
        $config = $settingsReader->getClassifiedsConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('La sección de avisos no está disponible.');
        }

        $user = $this->requireReader($site, $authorizationChecker);
        $pageContext = $pageContextFactory->build($site);
        $em = $siteEmFactory->forContext($site);
        $categories = $this->classifiedCategoryRepository($em)->findActiveOrdered();
        $defaultCategory = $categories[0] ?? null;

        $formData = [
            'categoryId' => $request->query->getInt('categoria', $defaultCategory?->getId() ?? 0) ?: '',
            'title' => '',
            'body' => '',
            'priceLabel' => '',
            'contactPhone' => '',
            'contactEmail' => $user->getEmail(),
        ];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('classified_create', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $userId = $user->getId();
            if ($userId === null) {
                throw $this->createAccessDeniedException();
            }
            $abuseRateLimiter->enforceUgc($request, 'classified', (string) $userId);

            $rawBody = trim((string) $request->request->get('body', ''));
            $formData = [
                'categoryId' => (string) $request->request->get('categoryId', ''),
                'title' => trim((string) $request->request->get('title', '')),
                'body' => $rawBody,
                'priceLabel' => trim((string) $request->request->get('priceLabel', '')),
                'contactPhone' => trim((string) $request->request->get('contactPhone', '')),
                'contactEmail' => trim((string) $request->request->get('contactEmail', '')),
            ];

            $category = $em->find(ClassifiedCategory::class, (int) $formData['categoryId']);
            $errors = [];
            if ($category === null || !$category->isActive()) {
                $errors['categoryId'] = 'Selecciona una categoría válida.';
            }
            if ($formData['title'] === '' || strlen($formData['title']) > 200) {
                $errors['title'] = 'El título debe tener entre 1 y 200 caracteres.';
            }
            $bodyLength = $richTextSanitizer->plainTextLength($rawBody);
            if ($richTextSanitizer->isEffectivelyEmpty($rawBody) || $bodyLength > 5000) {
                $errors['body'] = 'El texto debe tener entre 1 y 5000 caracteres.';
            }
            $body = $richTextSanitizer->sanitize($rawBody);
            if ($formData['priceLabel'] !== '' && strlen($formData['priceLabel']) > 80) {
                $errors['priceLabel'] = 'El precio es demasiado largo.';
            }
            if ($formData['contactPhone'] !== '' && strlen($formData['contactPhone']) > 40) {
                $errors['contactPhone'] = 'Teléfono demasiado largo.';
            }
            if ($formData['contactEmail'] !== '' && !filter_var($formData['contactEmail'], FILTER_VALIDATE_EMAIL)) {
                $errors['contactEmail'] = 'Email de contacto no válido.';
            }

            if ($errors === [] && $category !== null) {
                $userId = $user->getId();
                if ($userId === null) {
                    throw $this->createAccessDeniedException();
                }

                $slug = $slugAllocator->forClassifiedAd($em, $formData['title']);
                $ad = new ClassifiedAd(
                    $category,
                    $formData['title'],
                    $body,
                    $slug,
                    $userId,
                    $user->getName(),
                );
                $ad->setPriceLabel($formData['priceLabel'] !== '' ? $formData['priceLabel'] : null);
                $ad->setContactPhone($formData['contactPhone'] !== '' ? $formData['contactPhone'] : null);
                $ad->setContactEmail($formData['contactEmail'] !== '' ? $formData['contactEmail'] : null);

                $em->persist($ad);
                $em->flush();

                $this->addFlash('success', 'Tu anuncio ha sido publicado.');

                return $this->redirectToRoute('public_classifieds_show', ['slug' => $slug]);
            }

            return $this->render('public/classifieds/form.html.twig', $this->classifiedFormView(
                $site,
                $pageContext,
                $formData,
                $errors,
                $categories,
            ));
        }

        return $this->render('public/classifieds/form.html.twig', $this->classifiedFormView(
            $site,
            $pageContext,
            $formData,
            [],
            $categories,
        ));
    }

    #[Route('/avisos/{slug}', name: 'public_classifieds_show', methods: ['GET'])]
    public function show(
        string $slug,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        PublicRelatedNewsProvider $relatedNewsProvider,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getClassifiedsConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('La sección de avisos no está disponible.');
        }

        $em = $siteEmFactory->forContext($site);
        $ad = $this->classifiedRepository($em)->findVisibleBySlug($slug);
        if ($ad === null) {
            throw $this->createNotFoundException('Anuncio no encontrado.');
        }

        $pageContext = $pageContextFactory->build($site);
        $user = $this->getUser();
        $canDelete = $user instanceof User
            && $user->getId() !== null
            && $user->getId() === $ad->getUserId();
        $categories = $this->classifiedCategoryRepository($em)->findActiveOrdered();
        $relatedNews = $relatedNewsProvider->forCommunityPage($site);

        return $this->render('public/classifieds/show.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'ad' => $ad,
            'canDelete' => $canDelete,
            'categories' => $categories,
            'latestNews' => $relatedNews['latestNews'],
            'interestNews' => $relatedNews['interestNews'],
            'seoTitle' => $ad->getTitle().' — '.$site->name,
            'seoDescription' => mb_substr(strip_tags($ad->getBody()), 0, 160),
        ]);
    }

    #[Route('/avisos/{slug}/eliminar', name: 'public_classifieds_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        SiteAuthorizationChecker $authorizationChecker,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getClassifiedsConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException();
        }

        $user = $this->requireReader($site, $authorizationChecker);
        $userId = $user->getId();
        if ($userId === null) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('classified_delete_'.$slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $em = $siteEmFactory->forContext($site);
        $ad = $em->getRepository(ClassifiedAd::class)->findOneBy(['slug' => $slug]);
        if ($ad === null || $ad->getUserId() !== $userId) {
            throw $this->createNotFoundException();
        }

        $em->remove($ad);
        $em->flush();

        $this->addFlash('success', 'Anuncio eliminado.');

        return $this->redirectToRoute('public_classifieds');
    }

    private function renderClassifiedsIndex(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        SiteEntityManagerFactory $siteEmFactory,
        PublicRelatedNewsProvider $relatedNewsProvider,
        ?ClassifiedCategory $activeCategory,
        string $titlePrefix,
        string $descriptionPrefix,
    ): Response {
        $site = $holder->require();
        $config = $settingsReader->getClassifiedsConfig($site);
        if (!$config->enabled) {
            throw $this->createNotFoundException('La sección de avisos no está disponible.');
        }

        $em = $siteEmFactory->forContext($site);
        $ads = $this->classifiedRepository($em)->findVisibleLatest($activeCategory);
        $categories = $this->classifiedCategoryRepository($em)->findActiveOrdered();
        $pageContext = $pageContextFactory->build($site);
        $relatedNews = $relatedNewsProvider->forCommunityPage($site);

        return $this->render('public/classifieds/index.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'ads' => $ads,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'latestNews' => $relatedNews['latestNews'],
            'interestNews' => $relatedNews['interestNews'],
            'seoTitle' => $titlePrefix.$site->name,
            'seoDescription' => $descriptionPrefix.$site->name,
        ]);
    }

    /** @param array<string, mixed> $pageContext */
    private function classifiedFormView(
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
            'seoTitle' => 'Publicar anuncio — '.$site->name,
        ];
    }

    private function requireReader(SiteContext $site, SiteAuthorizationChecker $authorizationChecker): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if (!$authorizationChecker->canEngageAsReader($user, $site)) {
            throw $this->createAccessDeniedException('Solo los lectores registrados pueden publicar anuncios.');
        }

        return $user;
    }

    private function classifiedRepository(EntityManagerInterface $em): ClassifiedAdRepository
    {
        $repo = $em->getRepository(ClassifiedAd::class);
        if (!$repo instanceof ClassifiedAdRepository) {
            throw new \RuntimeException('ClassifiedAdRepository no disponible.');
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
