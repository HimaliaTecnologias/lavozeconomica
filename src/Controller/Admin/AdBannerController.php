<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\AdBanner;
use App\Entity\Site\ArticleTag;
use App\Entity\Site\Category;
use App\Entity\Site\Enum\AdCreativeType;
use App\Entity\Site\Enum\AdFormat;
use App\Entity\Site\Enum\AdPlacement;
use App\Editorial\AdBannerAnalyticsService;
use App\Editorial\AdBannerTextPresets;
use App\Security\SiteAuthorizationChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/ads')]
#[IsGranted('ROLE_USER')]
final class AdBannerController extends AbstractAdminController
{
    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly AdBannerAnalyticsService $analyticsService,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('/dashboard', name: 'admin_ads_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::ADS);
        $em = $this->siteEmFactory->forContext($site);
        $days = (int) $request->query->get('days', 30);
        $stats = $this->analyticsService->buildDashboard($em, $days);

        return $this->render('admin/ad/dashboard.html.twig', [
            'site' => $site,
            'stats' => $stats,
        ]);
    }

    #[Route('', name: 'admin_ads_index', methods: ['GET'])]
    public function index(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::ADS);
        $em = $this->siteEmFactory->forContext($site);
        $banners = $em->getRepository(AdBanner::class)->findBy([], ['sortOrder' => 'ASC', 'id' => 'ASC']);

        return $this->render('admin/ad/index.html.twig', [
            'site' => $site,
            'banners' => $banners,
            'placements' => AdPlacement::cases(),
            'placementGroups' => AdPlacement::groupOrder(),
            'categories' => $em->getRepository(Category::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']),
            'tags' => $em->getRepository(ArticleTag::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_ads_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::ADS);
        $em = $this->siteEmFactory->forContext($site);

        return $this->handleForm($request, $em, $site, null);
    }

    #[Route('/{id}/edit', name: 'admin_ads_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::ADS);
        $em = $this->siteEmFactory->forContext($site);
        $banner = $this->findOr404($em, $id);

        return $this->handleForm($request, $em, $site, $banner);
    }

    #[Route('/{id}/delete', name: 'admin_ads_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::ADS);
        $em = $this->siteEmFactory->forContext($site);
        $banner = $this->findOr404($em, $id);

        if (!$this->isCsrfTokenValid('delete_ad'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $em->remove($banner);
        $em->flush();

        $this->addFlash('success', 'Banner eliminado.');

        return $this->redirectToRoute('admin_ads_index');
    }

    private function handleForm(Request $request, EntityManagerInterface $em, \App\Core\Site\SiteContext $site, ?AdBanner $banner): Response
    {
        $isNew = $banner === null;
        $presets = AdBannerTextPresets::all();

        if ($request->isMethod('POST')) {
            $tokenId = $isNew ? 'ad_form_new' : 'ad_form_'.$banner->getId();
            if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $name = trim((string) $request->request->get('name', ''));
            $rawPlacements = $request->request->all('placements');
            if (!\is_array($rawPlacements)) {
                $rawPlacements = [];
            }
            $placements = [];
            foreach ($rawPlacements as $raw) {
                $placement = AdPlacement::tryFrom((string) $raw);
                if ($placement !== null) {
                    $placements[$placement->value] = $placement;
                }
            }
            $placements = array_values($placements);
            $format = AdFormat::tryFrom((string) $request->request->get('format', ''));
            $creativeType = AdCreativeType::tryFrom((string) $request->request->get('creativeType', 'image'));
            $imageUrl = trim((string) $request->request->get('imageUrl', ''));
            $linkUrl = trim((string) $request->request->get('linkUrl', ''));
            $htmlContent = trim((string) $request->request->get('htmlContent', ''));
            $altText = trim((string) $request->request->get('altText', ''));
            $headline = trim((string) $request->request->get('headline', ''));
            $bodyText = trim((string) $request->request->get('bodyText', ''));
            $ctaLabel = trim((string) $request->request->get('ctaLabel', ''));
            $bgColor = trim((string) $request->request->get('bgColor', ''));
            $textColor = trim((string) $request->request->get('textColor', ''));
            $accentColor = trim((string) $request->request->get('accentColor', ''));
            $presetKey = trim((string) $request->request->get('presetKey', ''));
            $sortOrder = (int) $request->request->get('sortOrder', 0);
            $isActive = $request->request->has('isActive');
            $categoryIds = $this->parseIdList($request->request->all('categoryIds'), $em, Category::class);
            $tagIds = $this->parseIdList($request->request->all('tagIds'), $em, ArticleTag::class);

            $errors = [];
            if ($name === '') {
                $errors['name'] = 'El nombre es obligatorio.';
            }
            if ($placements === []) {
                $errors['placements'] = 'Elige al menos una ubicación.';
            }
            if ($format === null) {
                $errors['format'] = 'Formato no válido.';
            }
            if ($creativeType === null) {
                $errors['creativeType'] = 'Tipo de banner no válido.';
            }

            if ($creativeType === AdCreativeType::Image) {
                if ($imageUrl === '') {
                    $errors['imageUrl'] = 'La URL de imagen es obligatoria.';
                } elseif (!$this->isValidAssetUrl($imageUrl)) {
                    $errors['imageUrl'] = 'URL de imagen no válida.';
                }
            } elseif ($creativeType === AdCreativeType::Html) {
                if ($htmlContent === '') {
                    $errors['content'] = 'El código HTML es obligatorio.';
                }
            } elseif ($creativeType === AdCreativeType::Text || $creativeType === AdCreativeType::Generated) {
                if ($headline === '') {
                    $errors['headline'] = 'El titular es obligatorio.';
                }
                if ($creativeType === AdCreativeType::Generated && $presetKey !== '' && AdBannerTextPresets::get($presetKey) === null) {
                    $errors['content'] = 'Plantilla autogenerada no válida.';
                }
            }

            if ($linkUrl !== '' && !$this->isValidLinkUrl($linkUrl)) {
                $errors['linkUrl'] = 'URL de destino no válida (usa https://… o una ruta /…).';
            }
            foreach (['bgColor' => $bgColor, 'textColor' => $textColor, 'accentColor' => $accentColor] as $field => $color) {
                if ($color !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    $errors[$field] = 'Color no válido.';
                }
            }

            if ($errors === []) {
                if ($isNew) {
                    $banner = new AdBanner($name, $placements[0], $format);
                    $em->persist($banner);
                } else {
                    $banner->setName($name)->setFormat($format);
                }

                $banner->setPlacements($placements)
                    ->setCategoryIds($categoryIds)
                    ->setTagIds($tagIds)
                    ->setCreativeType($creativeType)
                    ->setImageUrl($creativeType === AdCreativeType::Image && $imageUrl !== '' ? $imageUrl : null)
                    ->setLinkUrl($linkUrl !== '' ? $linkUrl : null)
                    ->setHtmlContent($creativeType === AdCreativeType::Html && $htmlContent !== '' ? $htmlContent : null)
                    ->setAltText($altText !== '' ? $altText : null)
                    ->setHeadline(\in_array($creativeType, [AdCreativeType::Text, AdCreativeType::Generated], true) && $headline !== '' ? $headline : null)
                    ->setBodyText(\in_array($creativeType, [AdCreativeType::Text, AdCreativeType::Generated], true) && $bodyText !== '' ? $bodyText : null)
                    ->setCtaLabel(\in_array($creativeType, [AdCreativeType::Text, AdCreativeType::Generated], true) && $ctaLabel !== '' ? $ctaLabel : null)
                    ->setBgColor(\in_array($creativeType, [AdCreativeType::Text, AdCreativeType::Generated], true) && $bgColor !== '' ? $bgColor : null)
                    ->setTextColor(\in_array($creativeType, [AdCreativeType::Text, AdCreativeType::Generated], true) && $textColor !== '' ? $textColor : null)
                    ->setAccentColor(\in_array($creativeType, [AdCreativeType::Text, AdCreativeType::Generated], true) && $accentColor !== '' ? $accentColor : null)
                    ->setPresetKey($creativeType === AdCreativeType::Generated && $presetKey !== '' ? $presetKey : null)
                    ->setSortOrder($sortOrder)
                    ->setIsActive($isActive)
                    ->setStartsAt($this->parseDate($request->request->get('startsAt')))
                    ->setEndsAt($this->parseDate($request->request->get('endsAt')));

                $em->flush();

                $this->addFlash('success', $isNew ? 'Banner creado.' : 'Banner actualizado.');

                return $this->redirectToRoute('admin_ads_index');
            }

            return $this->render('admin/ad/form.html.twig', $this->formViewData($site, $banner, $em, $presets, $request->request->all(), $errors));
        }

        return $this->render('admin/ad/form.html.twig', $this->formViewData($site, $banner, $em, $presets));
    }

    /**
     * @param array<string, mixed> $formData
     * @param array<string, string> $errors
     *
     * @return array<string, mixed>
     */
    private function formViewData(
        \App\Core\Site\SiteContext $site,
        ?AdBanner $banner,
        EntityManagerInterface $em,
        array $presets,
        array $formData = [],
        array $errors = [],
    ): array {
        return [
            'site' => $site,
            'banner' => $banner,
            'formData' => $formData,
            'errors' => $errors,
            'placements' => AdPlacement::cases(),
            'placementGroups' => AdPlacement::groupOrder(),
            'formats' => AdFormat::cases(),
            'creativeTypes' => AdCreativeType::cases(),
            'presets' => $presets,
            'categories' => $em->getRepository(Category::class)->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']),
            'tags' => $em->getRepository(ArticleTag::class)->findBy([], ['name' => 'ASC']),
        ];
    }

    /**
     * @param mixed $rawIds
     * @param class-string $entityClass
     *
     * @return list<int>
     */
    private function parseIdList(mixed $rawIds, EntityManagerInterface $em, string $entityClass): array
    {
        if (!\is_array($rawIds)) {
            return [];
        }

        $ids = [];
        foreach ($rawIds as $raw) {
            $id = (int) $raw;
            if ($id <= 0 || isset($ids[$id])) {
                continue;
            }
            if ($em->find($entityClass, $id) !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private function isValidAssetUrl(string $url): bool
    {
        return str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isValidLinkUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return !str_contains($url, '://');
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function findOr404(EntityManagerInterface $em, int $id): AdBanner
    {
        $banner = $em->find(AdBanner::class, $id);
        if ($banner === null) {
            throw $this->createNotFoundException('Banner no encontrado.');
        }

        return $banner;
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, new \DateTimeZone('Europe/Madrid'));
        } catch (\Throwable) {
            return null;
        }
    }
}
