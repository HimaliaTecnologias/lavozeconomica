<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Platform\Site;
use App\Entity\Platform\User;
use App\Entity\Platform\UserSiteRole;
use App\Security\SiteRole;
use Doctrine\ORM\EntityManagerInterface;

final class AccountRegistrationService
{
    public function __construct(
        private readonly AccountRegistrationMailer $mailer,
        private readonly SiteMailer $siteMailer,
    ) {}

    public function registerReader(
        SiteContext $site,
        Site $platformSite,
        EntityManagerInterface $platformEm,
        User $user,
    ): User {
        if (!$this->siteMailer->isOperational($site)) {
            throw new \RuntimeException(
                'El registro no está disponible: el correo del sitio no está configurado. Contacta con el periódico.',
            );
        }

        $user->issueEmailVerificationToken();

        $platformEm->persist($user);
        $platformEm->persist(new UserSiteRole($user, $platformSite, SiteRole::READER));
        $platformEm->flush();

        $this->mailer->sendVerification($site, $user);

        return $user;
    }

    public function resendVerification(SiteContext $site, EntityManagerInterface $platformEm, string $email): void
    {
        if (!$this->siteMailer->isOperational($site)) {
            throw new \RuntimeException('No se puede enviar el correo. El SMTP del sitio no está configurado.');
        }

        $user = $platformEm->getRepository(User::class)->findOneBy(['email' => strtolower(trim($email))]);
        if ($user === null || $user->isEmailVerified()) {
            throw new \InvalidArgumentException('No hay una cuenta pendiente de verificación con ese correo.');
        }

        $user->issueEmailVerificationToken();
        $platformEm->flush();

        $this->mailer->sendVerification($site, $user);
    }

    public function verifyEmail(EntityManagerInterface $platformEm, string $token): User
    {
        $user = $platformEm->getRepository(User::class)->findOneBy(['emailVerificationToken' => $token]);
        if ($user === null || !$user->isEmailVerificationTokenValid($token)) {
            throw new \InvalidArgumentException('El enlace de verificación no es válido o ha caducado.');
        }

        $user->markEmailVerified();
        $platformEm->flush();

        return $user;
    }
}
