<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Entity\Site\Media;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MediaEnhancementService
{
    public function __construct(
        private readonly ArticleImageQualityPolicy $qualityPolicy,
        private readonly ArticleImageMetadataReader $metadataReader,
        private readonly ArticleImageEnhancerChain $enhancerChain,
        private readonly MediaStoragePathResolver $pathResolver,
        private readonly MediaLinkedArticleSyncService $linkedArticleSync,
        private readonly int $jpegQuality = 92,
    ) {}

    public function enhance(SiteContext $site, Media $media, EntityManagerInterface $em, bool $force = false): void
    {
        $media->setEnhancementStatus(ArticleImageEnhancementStatus::Processing);
        $media->setEnhancementError(null);
        $em->flush();

        try {
            $sourcePath = $this->pathResolver->absolutePathFromPublic($media->getPublicPath());
            if (!is_file($sourcePath)) {
                throw new \RuntimeException('Archivo de medios no encontrado en disco.');
            }

            $width = $media->getWidth() ?? 0;
            $height = $media->getHeight() ?? 0;
            if ($width === 0 || $height === 0) {
                $dimensions = $this->metadataReader->readFromFile($sourcePath);
                $width = $dimensions['width'];
                $height = $dimensions['height'];
                $media->setWidth($width);
                $media->setHeight($height);
            }

            if (!$force && $this->qualityPolicy->meetsRequirements($width, $height, 0)) {
                $media->setEnhancementStatus(ArticleImageEnhancementStatus::Skipped);
                $media->setEnhancedAt(new \DateTimeImmutable());
                $em->flush();
                $this->linkedArticleSync->syncFromMedia($media, $em);
                $em->flush();

                return;
            }

            $target = $this->qualityPolicy->targetDimensions($width, $height, 0);
            $enhancedFilename = preg_replace('/\.[^.]+$/', '', $media->getStoredFilename()).'-enhanced.jpg';
            $absoluteDir = dirname($sourcePath);
            $absoluteDest = $absoluteDir.'/'.$enhancedFilename;
            $publicDir = dirname($media->getPublicPath());
            $publicPath = $publicDir.'/'.$enhancedFilename;

            $this->enhancerChain->enhance($sourcePath, $absoluteDest, $target['width'], $target['height'], $this->jpegQuality);

            $finalDimensions = $this->metadataReader->readFromFile($absoluteDest);
            if ($sourcePath !== $absoluteDest && is_file($sourcePath)) {
                @unlink($sourcePath);
            }

            $media->setStoredFilename($enhancedFilename);
            $media->setPublicPath($publicPath);
            $media->setMimeType('image/jpeg');
            $media->setSizeBytes((int) filesize($absoluteDest));
            $media->setWidth($finalDimensions['width']);
            $media->setHeight($finalDimensions['height']);
            $media->setEnhancementStatus(ArticleImageEnhancementStatus::Enhanced);
            $media->setEnhancedAt(new \DateTimeImmutable());
            $media->setEnhancementError(null);
            $em->flush();

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
