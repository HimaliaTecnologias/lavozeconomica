<?php

namespace App\Messenger\Handler;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Media;
use App\Editorial\MediaEnhancementService;
use App\Messenger\EnhanceMediaMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EnhanceMediaHandler
{
    public function __construct(
        private readonly SiteContextResolver $siteResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly MediaEnhancementService $enhancementService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(EnhanceMediaMessage $message): void
    {
        $context = $this->siteResolver->resolveByCode($message->siteCode);
        if ($context === null) {
            $this->logger->warning('Mejora de medio: sitio no encontrado.', [
                'site_code' => $message->siteCode,
                'media_id' => $message->mediaId,
            ]);

            return;
        }

        $em = $this->siteEmFactory->forContext($context);
        $media = $em->find(Media::class, $message->mediaId);
        if ($media === null) {
            $this->logger->warning('Mejora de medio: registro no encontrado.', [
                'site_code' => $message->siteCode,
                'media_id' => $message->mediaId,
            ]);

            return;
        }

        if ($media->isVideo()) {
            return;
        }

        try {
            $this->enhancementService->enhance($context, $media, $em);
        } catch (\Throwable $e) {
            $this->logger->error('Mejora de medio fallida.', [
                'site_code' => $message->siteCode,
                'media_id' => $message->mediaId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
