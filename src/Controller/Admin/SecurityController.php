<?php

namespace App\Controller\Admin;

use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteSettingsReader;
use App\Security\LoginContextListener;
use App\Security\SiteAuthorizationChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly SiteContextHolder $siteHolder,
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteAuthorizationChecker $authorizationChecker,
    ) {}

    #[Route('/panel/entrar', name: 'admin_login', methods: ['GET'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        $site = $this->siteHolder->get();

        if ($user instanceof \App\Entity\Platform\User && $site !== null) {
            if ($this->authorizationChecker->canAccessAdminPanel($user, $site)) {
                if ($this->authorizationChecker->isRedactorOnly($user, $site)) {
                    return $this->redirectToRoute('admin_articles_index');
                }

                return $this->redirectToRoute('admin_dashboard');
            }
        }

        LoginContextListener::markAdminLogin($request);

        $appearance = null;
        $theme = null;

        if ($site !== null) {
            $appearance = $this->settingsReader->getAppearance($site);
            $theme = $this->settingsReader->getTheme($site);
        }

        $staffOnly = $request->query->get('error') === 'staff_only';

        return $this->render('admin/login.html.twig', [
            'site' => $site,
            'appearance' => $appearance,
            'theme' => $theme,
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'staffOnly' => $staffOnly,
        ]);
    }

    #[Route('/admin/login', name: 'admin_login_legacy', methods: ['GET'])]
    public function loginLegacy(): Response
    {
        return $this->redirectToRoute('admin_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/admin/{path}', name: 'admin_panel_legacy', requirements: ['path' => '.*'], methods: ['GET', 'POST'], priority: -50)]
    public function adminPanelLegacy(Request $request, string $path = ''): Response
    {
        $target = '/gestor'.($path !== '' ? '/'.$path : '');
        $query = $request->getQueryString();
        if ($query) {
            $target .= '?'.$query;
        }

        return $this->redirect($target, Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/admin', name: 'admin_panel_legacy_root', methods: ['GET', 'POST'], priority: -50)]
    public function adminPanelLegacyRoot(): Response
    {
        return $this->redirectToRoute('admin_dashboard', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/gestor/login', name: 'admin_login_gestor_alias', methods: ['GET'])]
    public function loginGestorAlias(): Response
    {
        return $this->redirectToRoute('admin_login', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('Logout gestionado por el firewall de seguridad.');
    }
}
