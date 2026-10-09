<?php

namespace App\Controller;

use App\Core\Site\PublicPageContextFactory;
use App\Core\Site\SiteContextHolder;
use App\Core\Site\SiteSettingsReader;
use App\Editorial\SiteMailer;
use App\Security\AbuseRateLimiter;
use App\Security\RegistrationCaptchaValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicContactController extends AbstractController
{
    #[Route('/contacto', name: 'public_contact_submit', methods: ['POST'])]
    public function submit(
        Request $request,
        SiteContextHolder $holder,
        SiteMailer $siteMailer,
        SiteSettingsReader $settingsReader,
        AbuseRateLimiter $abuseRateLimiter,
        RegistrationCaptchaValidator $captchaValidator,
    ): Response {
        $site = $holder->require();

        if (!$this->isCsrfTokenValid('contact_form', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF inválido.');
        }

        $honeypot = trim((string) $request->request->get('website', ''));
        if ($honeypot !== '') {
            return $this->redirectToRoute('public_page_show', ['slug' => 'contacto']);
        }

        $abuseRateLimiter->enforcePublicForm($request, 'contact');

        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $subject = trim((string) $request->request->get('subject', ''));
        $message = trim((string) $request->request->get('message', ''));

        $errors = [];
        if (!$captchaValidator->validate($request, 'contact')) {
            $errors['captcha'] = 'Confirma que no eres un robot.';
        }
        if ($name === '') {
            $errors['name'] = 'Indica tu nombre.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Indica un email válido.';
        }
        if ($message === '') {
            $errors['message'] = 'Escribe tu mensaje.';
        }
        if (strlen($message) > 5000) {
            $errors['message'] = 'El mensaje es demasiado largo.';
        }
        if (!$request->request->has('privacy_accept')) {
            $errors['privacy_accept'] = 'Debes aceptar el aviso legal y la política de privacidad.';
        }

        if ($errors !== []) {
            $request->getSession()->set('contact_form_errors', $errors);
            $request->getSession()->set('contact_form_data', compact('name', 'email', 'subject', 'message'));

            return $this->redirectToRoute('public_page_show', ['slug' => 'contacto']);
        }

        $footer = $settingsReader->getFooterConfig($site);
        $mailConfig = $settingsReader->getMailConfig($site);
        $recipient = $mailConfig->contactRecipient !== '' ? $mailConfig->contactRecipient : $footer->contactEmail;

        if ($recipient === '') {
            $this->addFlash('error', 'El formulario de contacto no está disponible: configura un email de destino en el administrador.');

            return $this->redirectToRoute('public_page_show', ['slug' => 'contacto']);
        }

        try {
            $siteMailer->sendContactMessage($site, $name, $email, $subject, $message);
            $request->getSession()->remove('contact_form_errors');
            $request->getSession()->remove('contact_form_data');
            $this->addFlash('success', 'Tu mensaje se ha enviado correctamente. Te responderemos pronto.');
        } catch (\Throwable) {
            $this->addFlash('error', 'No se pudo enviar el mensaje. Inténtalo más tarde o escríbenos directamente por email.');
        }

        return $this->redirectToRoute('public_page_show', ['slug' => 'contacto']);
    }
}
