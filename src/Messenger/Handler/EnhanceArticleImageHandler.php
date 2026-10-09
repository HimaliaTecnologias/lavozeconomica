<?php

namespace App\Messenger\Handler;

use App\Core\Site\SiteContextResolver;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\ArticleImage;
use App\Editorial\ArticleImageEnhancementService;
use App\Messenger\EnhanceArticleImageMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EnhanceArticleImageHandler
{
    public function __construct(
        private readonly SiteContextResolver $siteResolver,
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly ArticleImageEnhancementService $enhancementService,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(EnhanceArticleImageMessage $message): void
    {
        $context = $this->siteResolver->resolveByCode($message->siteCode);
        if ($context === null) {
            $this->logger->warning('Mejora de imagen: sitio no encontrado.', [
                'site_code' => $message->siteCode,
                'image_id' => $message->articleImageId,
            ]);

            return;
        }

        $em = $this->siteEmFactory->forContext($context);
        $image = $em->find(ArticleImage::class, $message->articleImageId);
        if ($image === null) {
            $this->logger->warning('Mejora de imagen: registro no encontrado.', [
                'site_code' => $message->siteCode,
                'image_id' => $message->articleImageId,
            ]);

            return;
        }

        try {
            $this->enhancementService->enhance($context, $image, $em);
        } catch (\Throwable $e) {
            $this->logger->error('Mejora de imagen fallida.', [
                'site_code' => $message->siteCode,
                'image_id' => $message->articleImageId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
