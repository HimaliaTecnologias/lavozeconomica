<?php

namespace App\Editorial;

use App\Entity\Site\ArticleImage;
use App\Entity\Site\Media;
use App\Repository\Site\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MediaLibraryImportService
{
    public function __construct(
        private readonly MediaStoragePathResolver $pathResolver,
        private readonly ArticleImageMetadataReader $metadataReader,
    ) {}

    /**
     * Registra en la biblioteca las imágenes usadas en noticias que aún no tienen Media vinculado.
     *
     * @return array{imported: int, linked: int, skipped: int}
     */
    public function importFromArticleImages(EntityManagerInterface $em): array
    {
        $mediaRepo = $em->getRepository(Media::class);
        if (!$mediaRepo instanceof MediaRepository) {
            return ['imported' => 0, 'linked' => 0, 'skipped' => 0];
        }

        /** @var list<ArticleImage> $images */
        $images = $em->getRepository(ArticleImage::class)->findBy(['media' => null]);

        $imported = 0;
        $linked = 0;
        $skipped = 0;

        foreach ($images as $image) {
            $url = trim($image->getUrl());
            if ($url === '' || !$this->isImportableImageReference($url)) {
                ++$skipped;

                continue;
            }

            $media = $mediaRepo->findOneByPublicPath($url);
            if ($media === null) {
                $media = $this->createMediaFromArticleImage($url, $image);
                if ($media === null) {
                    ++$skipped;

                    continue;
                }

                $em->persist($media);
                ++$imported;
            }

            $alt = $image->getAlt();
            $caption = $image->getCaption();
            $image->applyFromLibraryMedia($media);
            if ($alt !== null && $alt !== '') {
                $image->setAlt($alt);
            }
            if ($caption !== null && $caption !== '') {
                $image->setCaption($caption);
            }

            ++$linked;
        }

        if ($imported > 0 || $linked > 0) {
            $em->flush();
        }

        return [
            'imported' => $imported,
            'linked' => $linked,
            'skipped' => $skipped,
        ];
    }

    public function countUnlinkedArticleImages(EntityManagerInterface $em): int
    {
        return (int) $em->getRepository(ArticleImage::class)->count(['media' => null]);
    }

    private function createMediaFromArticleImage(string $url, ArticleImage $image): ?Media
    {
        if (str_starts_with($url, '/media/')) {
            return $this->createMediaFromLocalPath($url, $image);
        }

        if (filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return $this->createMediaFromExternalUrl($url, $image);
        }

        return null;
    }

    private function createMediaFromLocalPath(string $publicPath, ArticleImage $image): ?Media
    {
        $absolutePath = $this->pathResolver->absolutePathFromPublic($publicPath);
        if (!is_file($absolutePath)) {
            return null;
        }

        $storedFilename = basename($publicPath);
        $mimeType = $this->detectMimeType($absolutePath);
        if ($mimeType === null || !str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $media = new Media(
            $storedFilename,
            $storedFilename,
            $mimeType,
            (int) filesize($absolutePath),
            $publicPath,
        );

        $this->applyArticleImageMetadata($media, $image);

        try {
            $dimensions = $this->metadataReader->readFromFile($absolutePath);
            $media->setWidth($dimensions['width']);
            $media->setHeight($dimensions['height']);
        } catch (\Throwable) {
            if ($image->getWidth() !== null) {
                $media->setWidth($image->getWidth());
            }
            if ($image->getHeight() !== null) {
                $media->setHeight($image->getHeight());
            }
        }

        return $media;
    }

    private function createMediaFromExternalUrl(string $url, ArticleImage $image): ?Media
    {
        $storedFilename = substr(hash('sha256', $url), 0, 32).'.jpg';

        $media = new Media(
            $storedFilename,
            $this->filenameFromUrl($url),
            'image/jpeg',
            0,
            $url,
        );

        $this->applyArticleImageMetadata($media, $image);

        if ($image->getWidth() !== null) {
            $media->setWidth($image->getWidth());
        }
        if ($image->getHeight() !== null) {
            $media->setHeight($image->getHeight());
        }

        return $media;
    }

    private function applyArticleImageMetadata(Media $media, ArticleImage $image): void
    {
        $articleTitle = $image->getArticle()->getTitle();
        $media->setTitle($articleTitle !== '' ? $articleTitle : null);

        if ($image->getAlt() !== null && $image->getAlt() !== '') {
            $media->setAlt($image->getAlt());
        }
        if ($image->getCaption() !== null && $image->getCaption() !== '') {
            $media->setCaption($image->getCaption());
        }

        $media->setEnhancementStatus($image->getEnhancementStatus());
        $media->setEnhancedAt($image->getEnhancedAt());
        $media->setEnhancementError($image->getEnhancementError());
    }

    private function isImportableImageReference(string $url): bool
    {
        if (str_starts_with($url, '/media/')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function detectMimeType(string $absolutePath): ?string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($absolutePath);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => null,
        };
    }

    private function filenameFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $basename = basename($path);
            if ($basename !== '' && $basename !== '/') {
                return $basename;
            }
        }

        return 'imagen-remota';
    }
}
