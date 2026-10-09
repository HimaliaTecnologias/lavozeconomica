<?php

namespace App\Controller\Admin;

use App\Entity\Site\Enum\SiteLinkGroup;
use App\Entity\Site\SiteLink;
use App\Repository\Site\SiteLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/gestor/links')]
#[IsGranted('ROLE_USER')]
final class SiteLinkController extends AbstractAdminController
{
    public function __construct(
        \App\Core\Site\SiteContextHolder $siteHolder,
        \App\Security\SiteAuthorizationChecker $authorizationChecker,
        private readonly \App\Core\Site\SiteEntityManagerFactory $siteEmFactory,
    ) {
        parent::__construct($siteHolder, $authorizationChecker);
    }

    #[Route('', name: 'admin_links_index', methods: ['GET'])]
    public function index(): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::LINKS);
        $em = $this->siteEmFactory->forContext($site);
        $links = $this->repository($em)->findAllOrdered();

        $grouped = [
            SiteLinkGroup::OtherMedia->value => [],
            SiteLinkGroup::Interest->value => [],
        ];
        foreach ($links as $link) {
            $grouped[$link->getGroup()->value][] = $link;
        }

        return $this->render('admin/link/index.html.twig', [
            'site' => $site,
            'grouped' => $grouped,
            'groups' => SiteLinkGroup::cases(),
        ]);
    }

    #[Route('/new', name: 'admin_links_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::LINKS);
        $em = $this->siteEmFactory->forContext($site);
        $defaultGroup = SiteLinkGroup::tryFrom((string) $request->query->get('group', '')) ?? SiteLinkGroup::Interest;

        if ($request->isMethod('POST')) {
            return $this->handleForm($request, $em, $site, null, $defaultGroup);
        }

        return $this->render('admin/link/form.html.twig', [
            'site' => $site,
            'link' => null,
            'groups' => SiteLinkGroup::cases(),
            'formData' => ['group' => $defaultGroup->value],
            'errors' => [],
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_links_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::LINKS);
        $em = $this->siteEmFactory->forContext($site);
        $link = $em->find(SiteLink::class, $id);
        if ($link === null) {
            throw $this->createNotFoundException('Enlace no encontrado.');
        }

        if ($request->isMethod('POST')) {
            return $this->handleForm($request, $em, $site, $link, $link->getGroup());
        }

        return $this->render('admin/link/form.html.twig', [
            'site' => $site,
            'link' => $link,
            'groups' => SiteLinkGroup::cases(),
            'formData' => [],
            'errors' => [],
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_links_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, int $id): Response
    {
        [$site] = $this->requirePermission(\App\Security\SitePermission::LINKS);
        $em = $this->siteEmFactory->forContext($site);

        if (!$this->isCsrfTokenValid('delete_link'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $link = $em->find(SiteLink::class, $id);
        if ($link !== null) {
            $em->remove($link);
            $em->flush();
            $this->addFlash('success', 'Enlace eliminado.');
        }

        return $this->redirectToRoute('admin_links_index');
    }

    private function handleForm(
        Request $request,
        EntityManagerInterface $em,
        \App\Core\Site\SiteContext $site,
        ?SiteLink $link,
        SiteLinkGroup $defaultGroup,
    ): Response {
        if (!$this->isCsrfTokenValid('link_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $title = trim((string) $request->request->get('title', ''));
        $url = trim((string) $request->request->get('url', ''));
        $logoUrl = trim((string) $request->request->get('logoUrl', ''));
        $group = SiteLinkGroup::tryFrom((string) $request->request->get('group', '')) ?? $defaultGroup;
        $sortOrder = (int) $request->request->get('sortOrder', 0);
        $isEnabled = $request->request->has('isEnabled');
        $showInFooter = $request->request->has('showInFooter');
        $showInSidebar = $request->request->has('showInSidebar');

        $errors = [];
        if ($title === '') {
            $errors['title'] = 'El título es obligatorio.';
        }
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            $errors['url'] = 'URL no válida.';
        }
        if ($logoUrl !== '' && !filter_var($logoUrl, FILTER_VALIDATE_URL)) {
            $errors['logoUrl'] = 'URL del logo no válida.';
        }

        $formData = [
            'title' => $title,
            'url' => $url,
            'logoUrl' => $logoUrl,
            'group' => $group->value,
            'sortOrder' => $sortOrder,
            'isEnabled' => $isEnabled,
            'showInFooter' => $showInFooter,
            'showInSidebar' => $showInSidebar,
        ];

        if ($errors !== []) {
            return $this->render('admin/link/form.html.twig', [
                'site' => $site,
                'link' => $link,
                'groups' => SiteLinkGroup::cases(),
                'formData' => $formData,
                'errors' => $errors,
            ]);
        }

        if ($link === null) {
            $link = new SiteLink($title, $url, $group);
            $em->persist($link);
        } else {
            $link->setTitle($title)->setUrl($url)->setGroup($group);
        }

        $link->setLogoUrl($logoUrl !== '' ? $logoUrl : null);
        $link->setSortOrder($sortOrder);
        $link->setIsEnabled($isEnabled);
        $link->setShowInFooter($showInFooter);
        $link->setShowInSidebar($showInSidebar);
        $em->flush();

        $this->addFlash('success', 'Enlace guardado.');

        return $this->redirectToRoute('admin_links_index');
    }

    private function repository(EntityManagerInterface $em): SiteLinkRepository
    {
        $repo = $em->getRepository(SiteLink::class);
        if (!$repo instanceof SiteLinkRepository) {
            throw new \RuntimeException('SiteLinkRepository no disponible.');
        }

        return $repo;
    }
}
