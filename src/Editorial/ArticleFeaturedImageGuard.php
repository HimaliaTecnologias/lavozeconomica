<?php

namespace App\Editorial;

use App\Entity\Site\Article;
use App\Entity\Site\ArticleImage;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Entity\Site\Media;

final class ArticleFeaturedImageGuard
{
    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function isPubliclyVisible(Article $article): bool
    {
        return $this->hasUsableHeroVisual($article);
    }

    /**
     * Imagen destacada usable en front (existe y el archivo local, si aplica, está en disco).
     */
    public function getUsableFeaturedImage(Article $article): ?ArticleImage
    {
        if ($article->isVideoReplacesFeatured()) {
            return null;
        }

        $featured = $article->getFeaturedImage();
        if ($featured === null || $this->imageIssue($featured) !== null) {
            return null;
        }

        return $featured;
    }

    public function hasUsableHeroVisual(Article $article): bool
    {
        if ($article->isVideoReplacesFeatured()) {
            $video = $article->getVideo();

            return $video !== null && $this->mediaPathIssue($video) === null;
        }

        return $this->getUsableFeaturedImage($article) !== null;
    }

    public function getIssue(Article $article): ?string
    {
        if ($article->isVideoReplacesFeatured()) {
            $video = $article->getVideo();
            if ($video === null) {
                return 'Vídeo destacado no configurado.';
            }

            $videoIssue = $this->mediaPathIssue($video);
            if ($videoIssue !== null) {
                return 'Vídeo destacado: '.$videoIssue;
            }

            return null;
        }

        $featured = $article->getFeaturedImage();
        if ($featured === null) {
            return 'Sin imagen destacada.';
        }

        return $this->imageIssue($featured);
    }

    private function imageIssue(ArticleImage $image): ?string
    {
        $url = trim($image->getUrl());
        if ($url === '') {
            return 'La imagen destacada no tiene URL.';
        }

        if ($image->getEnhancementStatus() === ArticleImageEnhancementStatus::Failed) {
            $detail = trim($image->getEnhancementError() ?? '');

            return $detail !== ''
                ? 'Error al procesar la imagen: '.$this->truncate($detail, 120)
                : 'Error al procesar la imagen destacada.';
        }

        $pathIssue = $this->localPathIssue($url);
        if ($pathIssue !== null) {
            return $pathIssue;
        }

        return null;
    }

    private function mediaPathIssue(Media $media): ?string
    {
        $path = trim($media->getPublicPath());

        return $this->localPathIssue($path);
    }

    private function localPathIssue(string $url): ?string
    {
        if ($url === '') {
            return 'archivo no disponible';
        }

        if (!str_starts_with($url, '/')) {
            return null;
        }

        $absolute = $this->projectDir.'/public'.$url;
        if (!is_file($absolute)) {
            return 'Archivo no encontrado en el servidor.';
        }

        $size = @filesize($absolute);
        if ($size === false || $size <= 0) {
            return 'Archivo de imagen dañado o vacío.';
        }

        return null;
    }

    private function truncate(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max - 1).'…';
    }
}
