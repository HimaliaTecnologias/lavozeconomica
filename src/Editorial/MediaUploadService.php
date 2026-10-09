<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Entity\Site\Media;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MediaUploadService
{
    private const MAX_IMAGE_BYTES = 20 * 1024 * 1024;
    private const MAX_VIDEO_BYTES = 100 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_IMAGE_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /** @var list<string> */
    private const ALLOWED_VIDEO_MIME = [
        'video/mp4',
        'video/webm',
    ];

    public function __construct(
        private readonly MediaStoragePathResolver $pathResolver,
        private readonly ArticleImageMetadataReader $metadataReader,
    ) {}

    public function upload(
        SiteContext $site,
        UploadedFile $file,
        EntityManagerInterface $em,
        ?int $uploadedByUserId = null,
    ): Media {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Archivo de subida inválido.');
        }

        $mimeType = $file->getMimeType() ?? '';
        $isVideo = in_array($mimeType, self::ALLOWED_VIDEO_MIME, true);
        $isImage = in_array($mimeType, self::ALLOWED_IMAGE_MIME, true);

        if (!$isVideo && !$isImage) {
            throw new \InvalidArgumentException('Solo se permiten imágenes (JPEG, PNG, WebP, GIF) o vídeos (MP4, WebM).');
        }

        $maxBytes = $isVideo ? self::MAX_VIDEO_BYTES : self::MAX_IMAGE_BYTES;
        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException($isVideo
                ? 'El vídeo supera el tamaño máximo de 100 MB.'
                : 'La imagen supera el tamaño máximo de 20 MB.');
        }

        $extension = $this->extensionForMime($mimeType, $file->getClientOriginalExtension());
        $dirs = $this->pathResolver->libraryDirectory($site);
        if (!is_dir($dirs['absoluteDir'])) {
            set_error_handler(static function (int $severity, string $message): bool {
                throw new \RuntimeException($message);
            });
            try {
                if (!mkdir($dirs['absoluteDir'], 0775, true) && !is_dir($dirs['absoluteDir'])) {
                    throw new \RuntimeException('No se pudo crear el directorio de la biblioteca.');
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'No se pudo crear el directorio de medios (revisa permisos de public/media): '.$e->getMessage(),
                    0,
                    $e instanceof \Exception ? $e : null,
                );
            } finally {
                restore_error_handler();
            }
        }

        if (!is_writable($dirs['absoluteDir'])) {
            throw new \RuntimeException('El directorio de medios no es escribible: '.$dirs['publicDir']);
        }

        $storedFilename = $this->pathResolver->generateStoredFilename($extension);
        $absolutePath = $dirs['absoluteDir'].'/'.$storedFilename;
        $publicPath = $dirs['publicDir'].'/'.$storedFilename;

        $file->move($dirs['absoluteDir'], $storedFilename);

        $media = new Media(
            $storedFilename,
            $file->getClientOriginalName(),
            $mimeType,
            (int) filesize($absolutePath),
            $publicPath,
        );
        $media->setTitle(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $media->setUploadedByUserId($uploadedByUserId);

        if ($isImage) {
            $dimensions = $this->metadataReader->readFromFile($absolutePath);
            $media->setWidth($dimensions['width']);
            $media->setHeight($dimensions['height']);
            $media->setEnhancementStatus(ArticleImageEnhancementStatus::Pending);
        } else {
            $media->setEnhancementStatus(ArticleImageEnhancementStatus::Skipped);
        }

        $em->persist($media);
        $em->flush();

        return $media;
    }

    private function extensionForMime(string $mimeType, string $clientExtension): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
        ];

        $ext = $map[$mimeType] ?? strtolower($clientExtension);
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? 'bin';

        return $ext !== '' ? $ext : 'bin';
    }
}
