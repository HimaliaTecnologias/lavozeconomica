<?php

namespace App\Editorial;

use App\Core\Site\Dto\SiteMailConfig;
use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\BodyRendererInterface;
use Symfony\Component\Mime\Email;

final class SiteMailer
{
    public function __construct(
        private readonly MailerInterface $defaultMailer,
        private readonly SiteSettingsReader $settingsReader,
        private readonly BodyRendererInterface $bodyRenderer,
    ) {}

    public function send(SiteContext $site, Email $email): void
    {
        if ($email instanceof TemplatedEmail && !$email->isRendered()) {
            $this->bodyRenderer->render($email);
        }

        $config = $this->settingsReader->getMailConfig($site);
        $this->mailerFor($site, $config)->send($email);
    }

    public function sendContactMessage(
        SiteContext $site,
        string $visitorName,
        string $visitorEmail,
        string $subject,
        string $message,
    ): void {
        $config = $this->settingsReader->getMailConfig($site);
        $footer = $this->settingsReader->getFooterConfig($site);
        $recipient = $config->contactRecipient !== '' ? $config->contactRecipient : $footer->contactEmail;

        if ($recipient === '') {
            throw new \RuntimeException('No hay un email de destino configurado para el formulario de contacto.');
        }

        $fromEmail = $config->resolvedFromEmail($site->domain);
        $fromName = $config->resolvedFromName($site->name);
        $safeSubject = $subject !== '' ? $subject : 'Mensaje desde el formulario de contacto';

        $body = "Nombre: {$visitorName}\n"
            ."Email: {$visitorEmail}\n"
            ."Asunto: {$safeSubject}\n\n"
            .$message;

        $email = (new Email())
            ->from(new Address($fromEmail, $fromName))
            ->replyTo(new Address($visitorEmail, $visitorName))
            ->to($recipient)
            ->subject('[Contacto] '.$safeSubject.' — '.$site->name)
            ->text($body);

        $this->send($site, $email);
    }

    public function sendTestMessage(SiteContext $site, string $recipient): void
    {
        $config = $this->settingsReader->getMailConfig($site);
        if (!$config->isConfigured()) {
            throw new \RuntimeException('Completa host, usuario y contraseña SMTP antes de probar.');
        }

        $fromEmail = $config->resolvedFromEmail($site->domain);
        $fromName = $config->resolvedFromName($site->name);

        $email = (new Email())
            ->from(new Address($fromEmail, $fromName))
            ->to($recipient)
            ->subject('Prueba SMTP — '.$site->name)
            ->text('Este es un mensaje de prueba enviado desde el panel de administración. Si lo recibes, la configuración SMTP funciona correctamente.');

        $this->send($site, $email);
    }

    public function isOperational(SiteContext $site): bool
    {
        $config = $this->settingsReader->getMailConfig($site);

        return $config->isConfigured();
    }

    private function mailerFor(SiteContext $site, SiteMailConfig $config): MailerInterface
    {
        if ($config->isConfigured()) {
            return new Mailer(Transport::fromDsn($config->buildDsn()));
        }

        return $this->defaultMailer;
    }
}
