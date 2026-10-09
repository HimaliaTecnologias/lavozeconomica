<?php

namespace App\Controller\Admin;

use App\Core\Site\Dto\SitePermissionsConfig;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteEntityManagerFactory;
use App\Core\Site\SiteSettingsReader;
use App\Core\Site\SiteSettingsWriter;
use App\Entity\Platform\Site;
use App\Entity\Platform\User;
use App\Entity\Platform\UserSiteRole;
use App\Entity\Site\AdminActivityLog;
use App\Repository\Site\AdminActivityLogRepository;
use App\Security\SiteAuthorizationChecker;
use App\Security\SitePermission;
use App\Security\SiteRole;
use App\Security\UserSiteRoleQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/users')]
#[IsGranted('ROLE_USER')]
final class UserController extends AbstractAdminController
{
    /** @var array<string, string> */
    public const ROLE_OPTIONS = [
        SiteRole::SITE_ADMIN => 'Administrador del periódico',
        SiteRole::REDACTOR => 'Redactor',
    ];

    public function __construct(
        SiteContextHolder $siteHolder,
        SiteAuthorizationChecker $authorizationChecker,
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserSiteRoleQuery $userSiteRoleQuery,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteSettingsWriter $settingsWriter,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_users_index', methods: ['GET'])]
    public function index(): Response
    {
        [$siteContext] = $this->requirePermission(SitePermission::USERS);
        $platformSite = $this->requirePlatformSite($siteContext->id);

        $assignments = $this->userSiteRoleQuery->findStaffForSite($platformSite);
        $userIds = [];
        foreach ($assignments as $assignment) {
            $id = $assignment->getUser()->getId();
            if ($id !== null) {
                $userIds[] = $id;
            }
        }

        $lastActivity = [];
        $em = $this->siteEmFactory->forContext($siteContext);
        $activityRepo = $em->getRepository(AdminActivityLog::class);
        if ($activityRepo instanceof AdminActivityLogRepository) {
            $lastActivity = $activityRepo->findLatestByUserIds($userIds);
        }

        $permissions = $this->settingsReader->getPermissionsConfig($siteContext);

        return $this->render('admin/user/index.html.twig', [
            'site' => $siteContext,
            'assignments' => $assignments,
            'roleOptions' => self::ROLE_OPTIONS,
            'roleLabels' => SiteRole::STAFF_LABELS,
            'lastActivity' => $lastActivity,
            'permissions' => $permissions,
            'permissionGroups' => SitePermission::GROUPS,
            'permissionLabels' => SitePermission::LABELS,
            'counts' => [
                'total' => \count($assignments),
                'admins' => \count(array_filter(
                    $assignments,
                    static fn (UserSiteRole $a): bool => SiteRole::isSiteAdmin($a->getRole()),
                )),
                'redactors' => \count(array_filter(
                    $assignments,
                    static fn (UserSiteRole $a): bool => !SiteRole::isSiteAdmin($a->getRole()),
                )),
            ],
        ]);
    }

    #[Route('/permissions', name: 'admin_users_permissions', methods: ['GET', 'POST'])]
    public function permissions(Request $request): Response
    {
        [$siteContext] = $this->requirePermission(SitePermission::USERS);
        $current = $this->settingsReader->getPermissionsConfig($siteContext);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('users_permissions', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Token CSRF inválido.');
            }

            $matrix = [];
            foreach ([SiteRole::SITE_ADMIN, SiteRole::REDACTOR] as $role) {
                $selected = $request->request->all('perm_'.$role);
                $selected = \is_array($selected) ? array_values(array_map('strval', $selected)) : [];
                $matrix[$role] = SitePermission::normalizeList($selected);
            }
            // Compatibilidad: editor = redactor
            $matrix[SiteRole::EDITOR] = $matrix[SiteRole::REDACTOR];

            foreach (SitePermission::LOCKED_FOR_SITE_ADMIN as $locked) {
                if (!\in_array($locked, $matrix[SiteRole::SITE_ADMIN], true)) {
                    $matrix[SiteRole::SITE_ADMIN][] = $locked;
                }
            }

            if ($matrix[SiteRole::SITE_ADMIN] === []) {
                $this->addFlash('error', 'El administrador debe conservar al menos un permiso.');

                return $this->redirectToRoute('admin_users_permissions');
            }

            $config = new SitePermissionsConfig($matrix);
            $this->settingsWriter->setMany($siteContext, [
                ['permissions', 'matrix_json', $config->toJson()],
            ]);

            $this->addFlash('success', 'Permisos por tipo de usuario guardados.');

            return $this->redirectToRoute('admin_users_permissions');
        }

        return $this->render('admin/user/permissions.html.twig', [
            'site' => $siteContext,
            'matrix' => $current->toMatrix(),
            'permissionGroups' => SitePermission::GROUPS,
            'permissionLabels' => SitePermission::LABELS,
            'lockedForAdmin' => SitePermission::LOCKED_FOR_SITE_ADMIN,
            'roleOptions' => self::ROLE_OPTIONS,
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        [$siteContext] = $this->requirePermission(SitePermission::USERS);
        $platformSite = $this->requirePlatformSite($siteContext->id);

        if ($request->isMethod('POST')) {
            return $this->handleCreate($request, $platformSite, $siteContext);
        }

        return $this->render('admin/user/form.html.twig', [
            'site' => $siteContext,
            'user' => null,
            'assignment' => null,
            'roleOptions' => self::ROLE_OPTIONS,
            'permissions' => $this->settingsReader->getPermissionsConfig($siteContext),
            'permissionGroups' => SitePermission::GROUPS,
            'permissionLabels' => SitePermission::LABELS,
            'errors' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        [$siteContext] = $this->requirePermission(SitePermission::USERS);
        $assignment = $this->platformEm->find(UserSiteRole::class, $id);
        if ($assignment === null || $assignment->getSite()->getId() !== $siteContext->id) {
            throw $this->createNotFoundException('Usuario no encontrado en este periódico.');
        }

        if (!SiteRole::isStaff($assignment->getRole())) {
            throw $this->createNotFoundException('Usuario no encontrado en este periódico.');
        }

        if ($request->isMethod('POST')) {
            return $this->handleEdit($request, $assignment, $siteContext);
        }

        $lastActivity = null;
        $uid = $assignment->getUser()->getId();
        if ($uid !== null) {
            $em = $this->siteEmFactory->forContext($siteContext);
            $activityRepo = $em->getRepository(AdminActivityLog::class);
            if ($activityRepo instanceof AdminActivityLogRepository) {
                $lastActivity = $activityRepo->findLatestByUserIds([$uid])[$uid] ?? null;
            }
        }

        return $this->render('admin/user/form.html.twig', [
            'site' => $siteContext,
            'user' => $assignment->getUser(),
            'assignment' => $assignment,
            'roleOptions' => self::ROLE_OPTIONS,
            'permissions' => $this->settingsReader->getPermissionsConfig($siteContext),
            'permissionGroups' => SitePermission::GROUPS,
            'permissionLabels' => SitePermission::LABELS,
            'lastActivity' => $lastActivity,
            'errors' => [],
        ]);
    }

    #[Route('/{id}/remove', name: 'admin_users_remove', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function remove(Request $request, int $id): Response
    {
        [$siteContext] = $this->requirePermission(SitePermission::USERS);
        $assignment = $this->platformEm->find(UserSiteRole::class, $id);
        if ($assignment === null || $assignment->getSite()->getId() !== $siteContext->id) {
            throw $this->createNotFoundException('Usuario no encontrado.');
        }

        if (!SiteRole::isStaff($assignment->getRole())) {
            throw $this->createNotFoundException('Usuario no encontrado.');
        }

        if (!$this->isCsrfTokenValid('remove_user'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $user = $assignment->getUser();
        $this->platformEm->remove($assignment);
        $this->platformEm->flush();

        $remaining = $this->platformEm->getRepository(UserSiteRole::class)->count(['user' => $user]);
        if ($remaining === 0 && !$user->isPlatformSuperadmin()) {
            $this->platformEm->remove($user);
            $this->platformEm->flush();
        }

        $this->addFlash('success', 'Usuario eliminado de este periódico.');

        return $this->redirectToRoute('admin_users_index');
    }

    private function handleCreate(Request $request, Site $platformSite, \App\Core\Site\SiteContext $siteContext): Response
    {
        if (!$this->isCsrfTokenValid('user_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $login = strtolower(trim((string) $request->request->get('login', '')));
        $name = trim((string) $request->request->get('name', ''));
        $password = (string) $request->request->get('password', '');
        $role = (string) $request->request->get('role', SiteRole::REDACTOR);
        if ($role === SiteRole::EDITOR) {
            $role = SiteRole::REDACTOR;
        }

        $errors = $this->validateUserForm($login, $name, $password, true, $role);
        if ($errors !== []) {
            return $this->render('admin/user/form.html.twig', [
                'site' => $siteContext,
                'user' => null,
                'assignment' => null,
                'roleOptions' => self::ROLE_OPTIONS,
                'permissions' => $this->settingsReader->getPermissionsConfig($siteContext),
                'permissionGroups' => SitePermission::GROUPS,
                'permissionLabels' => SitePermission::LABELS,
                'errors' => $errors,
                'formData' => $request->request->all(),
            ]);
        }

        $user = $this->platformEm->getRepository(User::class)->findOneBy(['email' => $login]);
        if ($user === null) {
            $user = new User($login, $name, '');
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
            $user->markEmailVerified();
            $this->platformEm->persist($user);
        } else {
            $existing = $this->platformEm->getRepository(UserSiteRole::class)->findOneBy([
                'user' => $user,
                'site' => $platformSite,
            ]);
            if ($existing !== null) {
                if ($existing->getRole() === SiteRole::READER) {
                    $existing->setRole($role);
                    $user->setName($name);
                    if ($password !== '') {
                        $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
                    }
                    $this->platformEm->flush();
                    $this->addFlash('success', 'Usuario promovido a rol editorial.');

                    return $this->redirectToRoute('admin_users_index');
                }

                $errors['login'] = 'Este usuario ya tiene acceso a este periódico.';

                return $this->render('admin/user/form.html.twig', [
                    'site' => $siteContext,
                    'user' => $user,
                    'assignment' => null,
                    'roleOptions' => self::ROLE_OPTIONS,
                    'permissions' => $this->settingsReader->getPermissionsConfig($siteContext),
                    'permissionGroups' => SitePermission::GROUPS,
                    'permissionLabels' => SitePermission::LABELS,
                    'errors' => $errors,
                    'formData' => $request->request->all(),
                ]);
            }
            $user->setName($name);
            if (!$user->isEmailVerified()) {
                $user->markEmailVerified();
            }
        }

        $this->platformEm->persist(new UserSiteRole($user, $platformSite, $role));
        $this->platformEm->flush();

        $this->addFlash('success', 'Usuario creado.');

        return $this->redirectToRoute('admin_users_index');
    }

    private function handleEdit(Request $request, UserSiteRole $assignment, \App\Core\Site\SiteContext $siteContext): Response
    {
        if (!$this->isCsrfTokenValid('user_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $user = $assignment->getUser();
        $name = trim((string) $request->request->get('name', ''));
        $password = (string) $request->request->get('password', '');
        $role = (string) $request->request->get('role', SiteRole::REDACTOR);
        if ($role === SiteRole::EDITOR) {
            $role = SiteRole::REDACTOR;
        }

        $errors = $this->validateUserForm($user->getEmail(), $name, $password, false, $role);
        if ($errors !== []) {
            return $this->render('admin/user/form.html.twig', [
                'site' => $siteContext,
                'user' => $user,
                'assignment' => $assignment,
                'roleOptions' => self::ROLE_OPTIONS,
                'permissions' => $this->settingsReader->getPermissionsConfig($siteContext),
                'permissionGroups' => SitePermission::GROUPS,
                'permissionLabels' => SitePermission::LABELS,
                'errors' => $errors,
            ]);
        }

        $user->setName($name);
        if ($password !== '') {
            $user->setPasswordHash($this->passwordHasher->hashPassword($user, $password));
        }
        $assignment->setRole($role);
        $this->platformEm->flush();

        $this->addFlash('success', 'Usuario actualizado.');

        return $this->redirectToRoute('admin_users_index');
    }

    /** @return array<string, string> */
    private function validateUserForm(string $login, string $name, string $password, bool $isNew, string $role): array
    {
        $errors = [];
        if ($login === '') {
            $errors['login'] = 'El usuario es obligatorio.';
        }
        if ($name === '') {
            $errors['name'] = 'El nombre es obligatorio.';
        }
        if ($isNew && strlen($password) < 4) {
            $errors['password'] = 'La contraseña debe tener al menos 4 caracteres.';
        }
        if (!isset(self::ROLE_OPTIONS[$role])) {
            $errors['role'] = 'Rol no válido.';
        }

        return $errors;
    }

    private function requirePlatformSite(int $siteId): Site
    {
        $site = $this->platformEm->find(Site::class, $siteId);
        if ($site === null) {
            throw $this->createNotFoundException('Periódico no encontrado.');
        }

        return $site;
    }
}
