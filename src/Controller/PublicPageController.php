<?php

namespace App\Controller;

use App\Core\Site\ArchiveSidebarProvider;
use App\Core\Site\CategorySectionsProvider;
use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteSettingsReader;
use App\Editorial\SitePageRegistry;
use App\Security\RegistrationCaptchaValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicPageController extends AbstractController
{
    #[Route(
        '/pagina/{slug}',
        name: 'public_page_legacy',
        requirements: ['slug' => SitePageRegistry::SLUG_PATTERN],
        methods: ['GET'],
        priority: 100,
    )]
    public function legacy(string $slug): Response
    {
        return $this->redirectToRoute('public_page_show', ['slug' => $slug], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route(
        '/{slug}',
        name: 'public_page_show',
        requirements: ['slug' => SitePageRegistry::SLUG_PATTERN],
        methods: ['GET'],
        priority: -100,
    )]
    public function show(
        string $slug,
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        SiteSettingsReader $settingsReader,
        ArchiveSidebarProvider $sidebarProvider,
        CategorySectionsProvider $categorySectionsProvider,
        RegistrationCaptchaValidator $captchaValidator,
    ): Response {
        if (!SitePageRegistry::isValidSlug($slug)) {
            throw $this->createNotFoundException('Página no encontrada.');
        }

        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $definition = SitePageRegistry::get($slug);
        if ($definition === null) {
            throw $this->createNotFoundException('Página no encontrada.');
        }

        $bodyHtml = $settingsReader->getPageHtml($site, $definition['settingKey']);
        $template = $slug === 'contacto' ? 'public/page/contact.html.twig' : 'public/page/show.html.twig';

        $session = $request->getSession();
        $contactFormErrors = $session->get('contact_form_errors', []);
        $contactFormData = $session->get('contact_form_data', []);
        $session->remove('contact_form_errors');
        $session->remove('contact_form_data');
        $sectionCount = max(2, min(4, $pageContext['layout']['homeCategoryArticlesCount'] ?? 4));

        $contactCaptcha = null;
        if ($slug === 'contacto') {
            $contactCaptcha = [
                'challenge' => $captchaValidator->issueMathChallenge('contact'),
                'recaptchaSiteKey' => $captchaValidator->getRecaptchaSiteKey(),
                'usesRecaptcha' => $captchaValidator->usesRecaptcha(),
            ];
        }

        $sidebar = $sidebarProvider->forSite($site);

        return $this->render($template, array_merge([
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'layout' => $pageContext['layout'],
            'footer' => $pageContext['footer'],
            'contentModulesConfig' => $pageContext['contentModulesConfig'],
            'pageTitle' => $definition['title'],
            'pageSlug' => $slug,
            'bodyHtml' => $bodyHtml,
            'mailConfig' => $settingsReader->getMailConfig($site),
            'contactFormErrors' => $contactFormErrors,
            'contactFormData' => $contactFormData,
            'captchaChallenge' => $contactCaptcha['challenge'] ?? null,
            'recaptchaSiteKey' => $contactCaptcha['recaptchaSiteKey'] ?? '',
            'usesRecaptcha' => $contactCaptcha['usesRecaptcha'] ?? false,
            'seoCanonical' => $this->generateUrl('public_page_show', ['slug' => $slug], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
            'seoDescription' => $this->pageDescription($slug, $settingsReader->getAppearance($site)->metaDescription),
            'categorySections' => $categorySectionsProvider->forNavCategories(
                $site,
                $pageContext['navCategories'],
                $sectionCount,
            ),
            'relatedArticles' => $sidebar['latestArticles'],
        ], $sidebar));
    }

    private function pageDescription(string $slug, string $siteDescription): string
    {
        $defaults = [
            'contacto' => 'Formulario de contacto y datos para escribir al medio.',
            'aviso-legal' => 'Aviso legal y condiciones de uso del sitio web.',
            'privacidad' => 'Política de privacidad y tratamiento de datos personales.',
            'cookies' => 'Política de cookies y tecnologías de seguimiento.',
            'quienes-somos' => 'Información sobre el medio y su equipo editorial.',
            'anunciantes' => 'Información para anunciantes y oportunidades comerciales en el medio.',
        ];

        return $defaults[$slug] ?? $siteDescription;
    }
}
