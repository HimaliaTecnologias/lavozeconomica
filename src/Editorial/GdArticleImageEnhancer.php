<?php

namespace App\Editorial;

final class GdArticleImageEnhancer implements ArticleImageEnhancer
{
    public function supports(): bool
    {
        return function_exists('imagecreatefromstring');
    }

    public function enhance(string $sourcePath, string $destinationPath, int $targetWidth, int $targetHeight, int $jpegQuality): void
    {
        if (!$this->supports()) {
            throw new \RuntimeException('GD no está disponible.');
        }

        $binary = file_get_contents($sourcePath);
        if ($binary === false) {
            throw new \RuntimeException('No se pudo leer la imagen de origen.');
        }

        $source = imagecreatefromstring($binary);
        if ($source === false) {
            throw new \RuntimeException('No se pudo decodificar la imagen de origen.');
        }

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($resized === false) {
            imagedestroy($source);
            throw new \RuntimeException('No se pudo crear el lienzo de destino.');
        }

        imagecopyresampled(
            $resized,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            imagesx($source),
            imagesy($source),
        );

        if (!imagejpeg($resized, $destinationPath, $jpegQuality)) {
            imagedestroy($source);
            imagedestroy($resized);
            throw new \RuntimeException('No se pudo guardar la imagen mejorada.');
        }

        imagedestroy($source);
        imagedestroy($resized);
    }
}
