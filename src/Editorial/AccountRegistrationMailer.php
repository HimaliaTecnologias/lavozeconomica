<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use App\Entity\Platform\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class AccountRegistrationMailer
{
    public function __construct(
        private readonly SiteMailer $siteMailer,
        private readonly SiteSettingsReader $settingsReader,
        private readonly RouterInterface $router,
    ) {}

    public function sendVerification(SiteContext $site, User $user): void
    {
        if ($user->getEmailVerificationToken() === null) {
            throw new \RuntimeException('El usuario no tiene token de verificación.');
        }

        $this->configureRouterContext($site);

        $verifyUrl = $this->router->generate(
            'cuenta_verify_email',
            ['token' => $user->getEmailVerificationToken()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $theme = $this->settingsReader->getTheme($site);
        $config = $this->settingsReader->getMailConfig($site);
        $fromEmail = $config->resolvedFromEmail($site->domain);
        $fromName = $config->resolvedFromName($site->name);

        $email = (new TemplatedEmail())
            ->from(new Address($fromEmail, $fromName !== '' ? $fromName : $site->name))
            ->to($user->getEmail())
            ->subject('Confirma tu cuenta — '.$site->name)
            ->htmlTemplate('email/account/verify.html.twig')
            ->text(sprintf(
                "Hola %s,\n\nConfirma tu correo para participar en %s:\n\n%s\n\nSi no creaste esta cuenta, ignora este mensaje. El enlace caduca en 48 horas.",
                $user->getName(),
                $site->name,
                $verifyUrl,
            ))
            ->context([
                'site' => $site,
                'theme' => $theme,
                'user' => $user,
                'verifyUrl' => $verifyUrl,
            ]);

        $this->siteMailer->send($site, $email);
    }

    private function configureRouterContext(SiteContext $site): void
    {
        $context = $this->router->getContext();
        $context->setHost($site->domain);
        $context->setScheme(str_contains($site->domain, 'localhost') ? 'http' : 'https');
    }
}
