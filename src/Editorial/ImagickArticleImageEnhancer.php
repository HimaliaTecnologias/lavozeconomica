<?php

namespace App\Editorial;

final class ImagickArticleImageEnhancer implements ArticleImageEnhancer
{
    public function supports(): bool
    {
        return class_exists(\Imagick::class);
    }

    public function enhance(string $sourcePath, string $destinationPath, int $targetWidth, int $targetHeight, int $jpegQuality): void
    {
        if (!$this->supports()) {
            throw new \RuntimeException('Imagick no está disponible.');
        }

        $imagick = new \Imagick($sourcePath);
        $imagick->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);

        if ($imagick->getImageWidth() !== $targetWidth || $imagick->getImageHeight() !== $targetHeight) {
            $imagick->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1, true);
        }

        $imagick->unsharpMaskImage(0.5, 0.5, 1.0, 0.05);
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality($jpegQuality);
        $imagick->stripImage();

        if (!$imagick->writeImage($destinationPath)) {
            throw new \RuntimeException('No se pudo guardar la imagen mejorada.');
        }

        $imagick->clear();
        $imagick->destroy();
    }
}
