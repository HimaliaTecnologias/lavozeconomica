<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;

final class HealthController extends AbstractController
{
    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(EntityManagerInterface $platformEntityManager): JsonResponse
    {
        try {
            $platformEntityManager->getConnection()->executeQuery('SELECT 1');
            $checks = ['platform_database' => 'ok'];
            $status = 'ok';
        } catch (\Throwable) {
            $checks = ['platform_database' => 'error'];
            $status = 'degraded';
        }

        return new JsonResponse(['status' => $status, 'checks' => $checks], $status === 'ok' ? 200 : 503);
    }
}
