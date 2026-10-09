<?php

namespace App\Security;

use App\Core\Site\SiteContextHolder;
use App\Entity\Platform\User;
use App\EventSubscriber\AdminLastRouteSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LoginSuccessEvent::class, method: 'onLoginSuccess')]
#[AsEventListener(event: LoginFailureEvent::class, method: 'onLoginFailure')]
#[AsEventListener(event: LogoutEvent::class, method: 'onLogout')]
final class LoginContextListener
{
    private const SESSION_KEY = 'login_context';

    public function __construct(
        private readonly SiteContextHolder $siteHolder,
        private readonly SiteAuthorizationChecker $authorizationChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {}

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $context = (string) $request->getSession()->get(self::SESSION_KEY, 'public');
        $request->getSession()->remove(self::SESSION_KEY);

        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $user->recordLogin($request->getClientIp());
        $this->platformEm->flush();

        $site = $this->siteHolder->get();
        if ($site === null) {
            return;
        }

        if ($context === 'admin') {
            if (!$this->authorizationChecker->canAccessAdminPanel($user, $site)) {
                $event->getRequest()->getSession()->invalidate();
                $event->setResponse(new RedirectResponse(
                    $this->urlGenerator->generate('admin_login').'?error=staff_only'
                ));

                return;
            }

            $session = $request->getSession();
            $lastRoute = $session->get(AdminLastRouteSubscriber::sessionRouteKey());
            $lastParams = $session->get(AdminLastRouteSubscriber::sessionParamsKey(), []);

            if (is_string($lastRoute) && $lastRoute !== '' && $lastRoute !== 'admin_login') {
                try {
                    $event->setResponse(new RedirectResponse(
                        $this->urlGenerator->generate($lastRoute, is_array($lastParams) ? $lastParams : [])
                    ));

                    return;
                } catch (\Throwable) {
                    // Ruta guardada inválida: usar destino por defecto.
                }
            }

            $target = $this->urlGenerator->generate(
                $this->authorizationChecker->preferredAdminHome($user, $site)
            );
            $event->setResponse(new RedirectResponse($target));

            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('cuenta_profile')));
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $context = (string) $request->getSession()->get(self::SESSION_KEY, 'public');
        if ($context === 'admin') {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_login')));
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        if (!$request instanceof Request) {
            return;
        }

        $request->getSession()->remove(self::SESSION_KEY);
    }

    public static function markAdminLogin(Request $request): void
    {
        $request->getSession()->set(self::SESSION_KEY, 'admin');
    }

    public static function markPublicLogin(Request $request): void
    {
        $request->getSession()->set(self::SESSION_KEY, 'public');
    }
}
