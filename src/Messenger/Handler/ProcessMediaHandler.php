<?php

namespace App\Messenger\Handler;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\Media;
use App\Editorial\MediaProcessingService;
use App\Messenger\ProcessMediaMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessMediaHandler
{
    public function __construct(
        private readonly SiteContextResolver $siteResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly MediaProcessingService $processingService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessMediaMessage $message): void
    {
        $context = $this->siteResolver->resolveByCode($message->siteCode);
        if ($context === null) {
            $this->logger->warning('Proceso de medio: sitio no encontrado.', [
                'site_code' => $message->siteCode,
                'media_id' => $message->mediaId,
            ]);

            return;
        }

        $em = $this->siteEmFactory->forContext($context);
        $media = $em->find(Media::class, $message->mediaId);
        if ($media === null) {
            $this->logger->warning('Proceso de medio: registro no encontrado.', [
                'site_code' => $message->siteCode,
                'media_id' => $message->mediaId,
            ]);

            return;
        }

        try {
            $this->processingService->process($context, $media, $em, $message->operation);
        } catch (\Throwable $e) {
            $this->logger->error('Proceso de medio fallido.', [
                'site_code' => $message->siteCode,
                'media_id' => $message->mediaId,
                'operation' => $message->operation,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
