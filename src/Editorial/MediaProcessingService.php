<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Entity\Site\Media;
use Doctrine\ORM\EntityManagerInterface;

final class MediaProcessingService
{
    public function __construct(
        private readonly MediaEnhancementService $enhancementService,
        private readonly MediaImageTransformer $transformer,
        private readonly MediaStoragePathResolver $pathResolver,
        private readonly ArticleImageMetadataReader $metadataReader,
        private readonly MediaLinkedArticleSyncService $linkedArticleSync,
    ) {}

    public function process(SiteContext $site, Media $media, EntityManagerInterface $em, string $operation): void
    {
        if ($operation === 'enhance' || $operation === 'enhance_force') {
            $this->enhancementService->enhance($site, $media, $em, $operation === 'enhance_force');
            $this->linkedArticleSync->syncFromMedia($media, $em);
            $em->flush();

            return;
        }

        if (!MediaImageTransformer::isValidOperation($operation)) {
            throw new \InvalidArgumentException('Operación no válida.');
        }

        $media->setEnhancementStatus(ArticleImageEnhancementStatus::Processing);
        $media->setEnhancementError(null);
        $em->flush();

        try {
            $absolutePath = $this->pathResolver->absolutePathFromPublic($media->getPublicPath());
            $this->transformer->transformInPlace($absolutePath, $operation);

            $dimensions = $this->metadataReader->readFromFile($absolutePath);
            $media->setWidth($dimensions['width']);
            $media->setHeight($dimensions['height']);
            $media->setSizeBytes((int) filesize($absolutePath));
            $media->setEnhancementStatus(ArticleImageEnhancementStatus::Enhanced);
            $media->setEnhancedAt(new \DateTimeImmutable());
            $media->setEnhancementError(null);

            $this->linkedArticleSync->syncFromMedia($media, $em);
            $em->flush();
        } catch (\Throwable $e) {
            $media->setEnhancementStatus(ArticleImageEnhancementStatus::Failed);
            $media->setEnhancementError($e->getMessage());
            $em->flush();

            throw $e;
        }
    }
}
