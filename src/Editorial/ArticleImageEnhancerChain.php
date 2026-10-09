<?php

namespace App\Editorial;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ArticleImageEnhancerChain
{
    /** @var list<ArticleImageEnhancer> */
    private array $enhancers;

    public function __construct(
        ImagickArticleImageEnhancer $imagickEnhancer,
        GdArticleImageEnhancer $gdEnhancer,
        #[Autowire('%env(IMAGE_ENHANCER_URL)%')]
        private readonly string $externalEnhancerUrl = '',
    ) {
        $this->enhancers = [$imagickEnhancer, $gdEnhancer];
    }

    public function enhance(string $sourcePath, string $destinationPath, int $targetWidth, int $targetHeight, int $jpegQuality): void
    {
        foreach ($this->enhancers as $enhancer) {
            if ($enhancer->supports()) {
                $enhancer->enhance($sourcePath, $destinationPath, $targetWidth, $targetHeight, $jpegQuality);

                return;
            }
        }

        throw new \RuntimeException('No hay motor de mejora de imágenes disponible (Imagick/GD).');
    }

    public function hasExternalEnhancer(): bool
    {
        return $this->externalEnhancerUrl !== '';
    }

    public function getExternalEnhancerUrl(): ?string
    {
        return $this->hasExternalEnhancer() ? $this->externalEnhancerUrl : null;
    }
}
