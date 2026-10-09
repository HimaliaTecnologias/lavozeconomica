<?php

namespace App\Admin;

use App\Entity\Platform\User;
use App\Entity\Site\AdminActivityLog;
use Doctrine\ORM\EntityManagerInterface;

final class AdminActivityLogger
{
    public function log(
        EntityManagerInterface $em,
        User $user,
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $entityLabel = null,
    ): void {
        $userId = $user->getId();
        if ($userId === null) {
            return;
        }

        $em->persist(new AdminActivityLog(
            $userId,
            $user->getName() ?: $user->getUserIdentifier(),
            $action,
            $entityType,
            $entityId,
            $entityLabel,
        ));
    }
}
