<?php

namespace App\Editorial;

interface ArticleImageEnhancer
{
    public function supports(): bool;

    /**
     * Escala y afina la imagen de origen hacia las dimensiones objetivo.
     */
    public function enhance(string $sourcePath, string $destinationPath, int $targetWidth, int $targetHeight, int $jpegQuality): void;
}
