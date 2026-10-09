<?php

namespace App\Security;

use App\Entity\Platform\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class VerifiedEmailUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isEmailVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Tu cuenta no está verificada. Revisa tu correo o solicita un nuevo enlace de activación.',
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
