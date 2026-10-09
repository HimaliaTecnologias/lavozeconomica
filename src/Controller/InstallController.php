<?php

namespace App\Controller;

use App\Install\Dto\InstallWizardData;
use App\Install\InstallationService;
use App\Install\InstallWizardSession;
use App\Install\InstallWizardValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/install')]
final class InstallController extends AbstractController
{
    public function __construct(
        private readonly InstallationService $installationService,
        private readonly InstallWizardSession $wizardSession,
        private readonly InstallWizardValidator $wizardValidator,
    ) {}

    #[Route('', name: 'install_welcome', methods: ['GET'])]
    public function welcome(): Response
    {
        if (!$this->installationService->needsInstallation()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('install/welcome.html.twig');
    }

    #[Route('/periodico', name: 'install_periodico', methods: ['GET', 'POST'])]
    public function periodico(Request $request): Response
    {
        if ($response = $this->assertNeedsInstallation()) {
            return $response;
        }

        $data = $this->wizardSession->get();

        if ($request->isMethod('POST')) {
            $data = $this->wizardSession->merge([
                'code' => strtolower(trim((string) $request->request->get('code', ''))),
                'name' => trim((string) $request->request->get('name', '')),
                'domain' => strtolower(trim((string) $request->request->get('domain', ''))),
                'locale' => (string) $request->request->get('locale', 'es'),
                'timezone' => (string) $request->request->get('timezone', 'Europe/Madrid'),
            ]);

            $errors = $this->wizardValidator->validatePeriodico($data);
            if ($errors === []) {
                return $this->redirectToRoute('install_apariencia');
            }

            return $this->render('install/periodico.html.twig', [
                'data' => $data,
                'errors' => $errors,
            ]);
        }

        if ($data->domain === '' && $request->getHost() !== '') {
            $data->domain = strtolower($request->getHost());
        }

        return $this->render('install/periodico.html.twig', [
            'data' => $data,
            'errors' => [],
        ]);
    }

    #[Route('/apariencia', name: 'install_apariencia', methods: ['GET', 'POST'])]
    public function apariencia(Request $request): Response
    {
        if ($response = $this->assertNeedsInstallation()) {
            return $response;
        }

        $data = $this->wizardSession->get();
        if ($data->code === '' || $data->name === '') {
            return $this->redirectToRoute('install_periodico');
        }

        if ($request->isMethod('POST')) {
            $data = $this->wizardSession->merge([
                'tagline' => trim((string) $request->request->get('tagline', '')),
                'primaryColor' => (string) $request->request->get('primaryColor', '#1a365d'),
                'secondaryColor' => (string) $request->request->get('secondaryColor', '#2c5282'),
            ]);

            $errors = $this->wizardValidator->validateApariencia($data);
            if ($errors === []) {
                return $this->redirectToRoute('install_admin');
            }

            return $this->render('install/apariencia.html.twig', [
                'data' => $data,
                'errors' => $errors,
            ]);
        }

        return $this->render('install/apariencia.html.twig', [
            'data' => $data,
            'errors' => [],
        ]);
    }

    #[Route('/admin', name: 'install_admin', methods: ['GET', 'POST'])]
    public function admin(Request $request): Response
    {
        if ($response = $this->assertNeedsInstallation()) {
            return $response;
        }

        $data = $this->wizardSession->get();
        if ($data->code === '') {
            return $this->redirectToRoute('install_periodico');
        }

        if ($request->isMethod('POST')) {
            $data = $this->wizardSession->merge([
                'adminName' => trim((string) $request->request->get('adminName', '')),
                'adminEmail' => strtolower(trim((string) $request->request->get('adminEmail', ''))),
                'adminPassword' => (string) $request->request->get('adminPassword', ''),
            ]);

            $errors = $this->wizardValidator->validateAdmin($data);
            if ($errors === []) {
                return $this->redirectToRoute('install_resumen');
            }

            return $this->render('install/admin.html.twig', [
                'data' => $data,
                'errors' => $errors,
            ]);
        }

        return $this->render('install/admin.html.twig', [
            'data' => $data,
            'errors' => [],
        ]);
    }

    #[Route('/resumen', name: 'install_resumen', methods: ['GET'])]
    public function resumen(): Response
    {
        if ($response = $this->assertNeedsInstallation()) {
            return $response;
        }

        $data = $this->wizardSession->get();
        if ($data->adminEmail === '') {
            return $this->redirectToRoute('install_admin');
        }

        return $this->render('install/resumen.html.twig', [
            'data' => $data,
        ]);
    }

    #[Route('/finalizar', name: 'install_finalizar', methods: ['POST'])]
    public function finalizar(Request $request): Response
    {
        if ($response = $this->assertNeedsInstallation()) {
            return $response;
        }

        if (!$this->isCsrfTokenValid('install_finalize', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $data = $this->wizardSession->get();
        $errors = array_merge(
            $this->wizardValidator->validatePeriodico($data),
            $this->wizardValidator->validateApariencia($data),
            $this->wizardValidator->validateAdmin($data),
        );

        if ($errors !== []) {
            $this->addFlash('error', 'Revisa los datos del asistente antes de finalizar.');

            return $this->redirectToRoute('install_periodico');
        }

        try {
            $site = $this->installationService->install($data);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Error durante la instalación: '.$e->getMessage());

            return $this->redirectToRoute('install_resumen');
        }

        $this->wizardSession->clear();

        return $this->render('install/completado.html.twig', [
            'site' => $site,
            'adminEmail' => $data->adminEmail,
        ]);
    }

    private function assertNeedsInstallation(): ?Response
    {
        if (!$this->installationService->needsInstallation()) {
            return $this->redirectToRoute('home');
        }

        return null;
    }
}
