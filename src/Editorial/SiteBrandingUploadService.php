<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SiteBrandingUploadService
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    private const IMAGE_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
    ];

    /** @var list<string> */
    private const FAVICON_MIME = [
        'image/png',
        'image/jpeg',
        'image/x-icon',
        'image/vnd.microsoft.icon',
        'image/webp',
    ];

    public function __construct(
        private readonly MediaStoragePathResolver $pathResolver,
    ) {}

    public function upload(SiteContext $site, UploadedFile $file, string $kind): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Archivo de subida inválido.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('El archivo supera el tamaño máximo de 5 MB.');
        }

        $mimeType = $file->getMimeType() ?? '';
        $allowed = $kind === 'favicon' ? self::FAVICON_MIME : self::IMAGE_MIME;
        if (!in_array($mimeType, $allowed, true)) {
            throw new \InvalidArgumentException('Tipo de archivo no permitido para este logo.');
        }

        $extension = $this->extensionForMime($mimeType, $file->getClientOriginalExtension());
        $dirs = $this->pathResolver->brandingDirectory($site);
        if (!is_dir($dirs['absoluteDir']) && !mkdir($dirs['absoluteDir'], 0775, true) && !is_dir($dirs['absoluteDir'])) {
            throw new \RuntimeException('No se pudo crear el directorio de branding.');
        }

        $storedFilename = $kind.'_'.$this->pathResolver->generateStoredFilename($extension);
        $file->move($dirs['absoluteDir'], $storedFilename);

        return $dirs['publicDir'].'/'.$storedFilename;
    }

    private function extensionForMime(string $mimeType, string $clientExtension): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'image/x-icon', 'image/vnd.microsoft.icon' => 'ico',
            default => strtolower(preg_replace('/[^a-z0-9]/', '', $clientExtension) ?? 'png') ?: 'png',
        };
    }
}
