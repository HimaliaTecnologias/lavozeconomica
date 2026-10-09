<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Platform\Site;
use App\Entity\Site\Article;
use App\Editorial\AccountRegistrationService;
use App\Editorial\UserAccountContentService;
use App\Entity\Platform\User;
use App\Repository\Site\ArticleRepository;
use App\Security\AbuseRateLimiter;
use App\Security\LoginContextListener;
use App\Security\RegistrationCaptchaValidator;
use App\Security\SiteAuthorizationChecker;
use App\Security\SiteRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class PublicAccountController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SiteAuthorizationChecker $authorizationChecker,
        private readonly AccountRegistrationService $registrationService,
        private readonly RegistrationCaptchaValidator $captchaValidator,
        private readonly UserAccountContentService $accountContentService,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly AbuseRateLimiter $abuseRateLimiter,
    ) {}

    #[Route('/cuenta/entrar', name: 'cuenta_login', methods: ['GET'])]
    public function login(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
        AuthenticationUtils $authenticationUtils,
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('cuenta_profile');
        }

        LoginContextListener::markPublicLogin($request);

        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);

        return $this->render('public/account/login.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'sidebarArticles' => $this->loadLatestNews($site, 8),
        ]);
    }

    #[Route('/cuenta/registro', name: 'cuenta_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('cuenta_profile');
        }

        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $errors = [];
        $captchaChallenge = $request->isMethod('GET')
            ? $this->captchaValidator->issueMathChallenge()
            : null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $this->abuseRateLimiter->enforcePublicForm($request, 'register');

            $email = strtolower(trim((string) $request->request->get('email', '')));
            $name = trim((string) $request->request->get('name', ''));
            $password = (string) $request->request->get('password', '');

            if (!$this->captchaValidator->validate($request)) {
                $errors['captcha'] = 'Confirma que no eres un robot (resuelve la operación o completa el captcha).';
                $captchaChallenge = $this->captchaValidator->issueMathChallenge();
            }

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Introduce un correo válido.';
            }
            if ($name === '') {
                $errors['name'] = 'El nombre es obligatorio.';
            }
            if (strlen($password) < 8) {
                $errors['password'] = 'La contraseña debe tener al menos 8 caracteres.';
            }

            if ($errors === []) {
                $existing = $this->platformEm->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existing !== null) {
                    if (!$existing->isEmailVerified()) {
                        try {
                            $this->registrationService->resendVerification($site, $this->platformEm, $email);
                            $request->getSession()->set('pending_verify_email', $email);

                            return $this->redirectToRoute('cuenta_register_pending');
                        } catch (\Throwable $e) {
                            $errors['email'] = $e->getMessage();
                        }
                    } else {
                        $errors['email'] = 'Ya existe una cuenta con este correo. Inicia sesión.';
                    }
                } else {
                    $platformSite = $this->platformEm->find(Site::class, $site->id);
                    if ($platformSite === null) {
                        throw $this->createNotFoundException('Periódico no encontrado.');
                    }

                    try {
                        $user = new User($email, $name, '');
                        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
                        $this->registrationService->registerReader(
                            $site,
                            $platformSite,
                            $this->platformEm,
                            $user,
                        );
                        $request->getSession()->set('pending_verify_email', $email);

                        return $this->redirectToRoute('cuenta_register_pending');
                    } catch (\Throwable $e) {
                        $errors['general'] = $e->getMessage();
                        $captchaChallenge = $this->captchaValidator->issueMathChallenge();
                    }
                }
            } else {
                if ($captchaChallenge === null && !$this->captchaValidator->usesRecaptcha()) {
                    $captchaChallenge = $this->captchaValidator->issueMathChallenge();
                }
            }
        }

        return $this->render('public/account/register.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'errors' => $errors,
            'formData' => $request->request->all(),
            'captchaChallenge' => $captchaChallenge,
            'recaptchaSiteKey' => $this->captchaValidator->getRecaptchaSiteKey(),
            'usesRecaptcha' => $this->captchaValidator->usesRecaptcha(),
            'sidebarArticles' => $this->loadLatestNews($site, 8),
        ]);
    }

    #[Route('/cuenta/registro-pendiente', name: 'cuenta_register_pending', methods: ['GET'])]
    public function registerPending(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $email = (string) $request->getSession()->get('pending_verify_email', '');

        return $this->render('public/account/register_pending.html.twig', [
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'pendingEmail' => $email,
            'captchaChallenge' => $this->captchaValidator->issueMathChallenge(),
            'recaptchaSiteKey' => $this->captchaValidator->getRecaptchaSiteKey(),
            'usesRecaptcha' => $this->captchaValidator->usesRecaptcha(),
        ]);
    }

    #[Route('/cuenta/reenviar-verificacion', name: 'cuenta_resend_verification', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        SiteContextHolder $holder,
    ): Response {
        if (!$this->isCsrfTokenValid('resend_verification', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $this->abuseRateLimiter->enforcePublicForm($request, 'resend-verify');

        $site = $holder->require();
        $email = strtolower(trim((string) $request->request->get('email', '')));

        if (!$this->captchaValidator->validate($request)) {
            $this->addFlash('error', 'No se pudo verificar el captcha. Inténtalo de nuevo.');

            return $this->redirectToRoute('cuenta_register_pending');
        }

        try {
            $this->registrationService->resendVerification($site, $this->platformEm, $email);
            $request->getSession()->set('pending_verify_email', $email);
            $this->addFlash('success', 'Hemos enviado un nuevo enlace de verificación a tu correo.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('cuenta_register_pending');
    }

    #[Route('/cuenta/verificar-email/{token}', name: 'cuenta_verify_email', methods: ['GET'])]
    public function verifyEmail(
        string $token,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);

        try {
            $this->registrationService->verifyEmail($this->platformEm, $token);
            $this->addFlash('success', 'Cuenta verificada. Ya puedes iniciar sesión.');

            return $this->redirectToRoute('cuenta_login');
        } catch (\InvalidArgumentException $e) {
            return $this->render('public/account/verify_failed.html.twig', [
                'site' => $site,
                'theme' => $pageContext['theme'],
                'appearance' => $pageContext['appearance'],
                'navCategories' => $pageContext['navCategories'],
                'errorMessage' => $e->getMessage(),
            ]);
        }
    }

    #[Route('/cuenta', name: 'cuenta_profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function profile(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        [$site, $user, $pageContext, $roleLabel, $canAccessAdmin] = $this->accountContext($holder, $pageContextFactory);
        $activity = $this->accountContentService->countActivity($site, $user->getId() ?? 0);

        return $this->render('public/account/dashboard.html.twig', $this->accountViewData(
            $site,
            $pageContext,
            $user,
            $roleLabel,
            $canAccessAdmin,
            'cuenta_profile',
            [
                'activity' => $activity,
            ],
        ));
    }

    #[Route('/cuenta/temas-foro', name: 'cuenta_forum_topics', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function forumTopics(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        [$site, $user, $pageContext, $roleLabel, $canAccessAdmin] = $this->accountContext($holder, $pageContextFactory);

        return $this->render('public/account/forum_topics.html.twig', $this->accountViewData(
            $site,
            $pageContext,
            $user,
            $roleLabel,
            $canAccessAdmin,
            'cuenta_forum_topics',
            [
                'topics' => $this->accountContentService->forumTopics($site, $user->getId() ?? 0),
            ],
        ));
    }

    #[Route('/cuenta/respuestas-foro', name: 'cuenta_forum_posts', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function forumPosts(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        [$site, $user, $pageContext, $roleLabel, $canAccessAdmin] = $this->accountContext($holder, $pageContextFactory);

        return $this->render('public/account/forum_posts.html.twig', $this->accountViewData(
            $site,
            $pageContext,
            $user,
            $roleLabel,
            $canAccessAdmin,
            'cuenta_forum_posts',
            [
                'posts' => $this->accountContentService->forumPosts($site, $user->getId() ?? 0),
            ],
        ));
    }

    #[Route('/cuenta/comentarios', name: 'cuenta_comments', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function comments(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        [$site, $user, $pageContext, $roleLabel, $canAccessAdmin] = $this->accountContext($holder, $pageContextFactory);

        return $this->render('public/account/comments.html.twig', $this->accountViewData(
            $site,
            $pageContext,
            $user,
            $roleLabel,
            $canAccessAdmin,
            'cuenta_comments',
            [
                'comments' => $this->accountContentService->articleComments($site, $user->getId() ?? 0),
            ],
        ));
    }

    #[Route('/cuenta/anuncios', name: 'cuenta_classifieds', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function classifieds(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        [$site, $user, $pageContext, $roleLabel, $canAccessAdmin] = $this->accountContext($holder, $pageContextFactory);

        return $this->render('public/account/classifieds.html.twig', $this->accountViewData(
            $site,
            $pageContext,
            $user,
            $roleLabel,
            $canAccessAdmin,
            'cuenta_classifieds',
            [
                'ads' => $this->accountContentService->classifiedAds($site, $user->getId() ?? 0),
            ],
        ));
    }

    #[Route('/cuenta/contrasena', name: 'cuenta_password', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(
        Request $request,
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): Response {
        [$site, $user, $pageContext, $roleLabel, $canAccessAdmin] = $this->accountContext($holder, $pageContextFactory);
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account_password', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $currentPassword = (string) $request->request->get('current_password', '');
            $newPassword = (string) $request->request->get('new_password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                $errors['current_password'] = 'La contraseña actual no es correcta.';
            }
            if (strlen($newPassword) < 8) {
                $errors['new_password'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
            }
            if ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Las contraseñas nuevas no coinciden.';
            }

            if ($errors === []) {
                $user->setPasswordHash($this->passwordHasher->hashPassword($user, $newPassword));
                $this->platformEm->flush();
                $this->addFlash('success', 'Contraseña actualizada correctamente.');

                return $this->redirectToRoute('cuenta_password');
            }
        }

        return $this->render('public/account/password.html.twig', $this->accountViewData(
            $site,
            $pageContext,
            $user,
            $roleLabel,
            $canAccessAdmin,
            'cuenta_password',
            [
                'errors' => $errors,
            ],
        ));
    }

    /**
     * @return array{0: \App\Core\Site\SiteContext, 1: User, 2: array<string, mixed>, 3: string, 4: bool}
     */
    private function accountContext(
        SiteContextHolder $holder,
        PublicPageContextFactory $pageContextFactory,
    ): array {
        $site = $holder->require();
        $pageContext = $pageContextFactory->build($site);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $role = $this->authorizationChecker->getSiteRole($user, $site);
        $roleLabel = match ($role) {
            SiteRole::SITE_ADMIN => 'Administrador',
            SiteRole::REDACTOR, SiteRole::EDITOR => 'Redactor',
            SiteRole::READER => 'Lector registrado',
            default => 'Usuario',
        };

        return [
            $site,
            $user,
            $pageContext,
            $roleLabel,
            $this->authorizationChecker->canAccessAdminPanel($user, $site),
        ];
    }

    /**
     * @param array<string, mixed> $pageContext
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function accountViewData(
        \App\Core\Site\SiteContext $site,
        array $pageContext,
        User $user,
        string $roleLabel,
        bool $canAccessAdmin,
        string $activeSection,
        array $extra = [],
    ): array {
        $latestNews = $this->loadLatestNews($site);

        return array_merge([
            'site' => $site,
            'theme' => $pageContext['theme'],
            'appearance' => $pageContext['appearance'],
            'navCategories' => $pageContext['navCategories'],
            'accountUser' => $user,
            'roleLabel' => $roleLabel,
            'canAccessAdmin' => $canAccessAdmin,
            'accountActiveSection' => $activeSection,
            'latestNews' => $latestNews,
            'interestNews' => $this->loadInterestNews($site, $latestNews),
        ], $extra);
    }

    /** @return list<Article> */
    private function loadLatestNews(SiteContext $site, int $limit = 6): array
    {
        $repo = $this->articleRepository($site);
        if ($repo === null) {
            return [];
        }

        return $repo->findPublishedLatest($limit);
    }

    /** @return list<Article> */
    /** @param list<Article> $latestNews */
    private function loadInterestNews(SiteContext $site, array $latestNews, int $limit = 6): array
    {
        $repo = $this->articleRepository($site);
        if ($repo === null) {
            return [];
        }

        $excludeIds = [];
        foreach ($latestNews as $article) {
            $id = $article->getId();
            if ($id !== null) {
                $excludeIds[] = $id;
            }
        }

        return $repo->findPublishedRandom($limit, $excludeIds);
    }

    private function articleRepository(SiteContext $site): ?ArticleRepository
    {
        $repo = $this->siteEmFactory->forContext($site)->getRepository(Article::class);
        if (!$repo instanceof ArticleRepository) {
            return null;
        }

        return $repo;
    }

    #[Route('/login_check', name: 'login_check', methods: ['POST'])]
    public function loginCheck(): void
    {
        throw new \LogicException('Autenticación gestionada por el firewall de seguridad.');
    }
}
