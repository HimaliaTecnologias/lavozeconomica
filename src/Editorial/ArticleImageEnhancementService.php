<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\ArticleImage;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ArticleImageEnhancementService
{
    private const MAX_DOWNLOAD_BYTES = 15 * 1024 * 1024;

    public function __construct(
        private readonly ArticleImageQualityPolicy $qualityPolicy,
        private readonly ArticleImageMetadataReader $metadataReader,
        private readonly ArticleImageEnhancerChain $enhancerChain,
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly int $jpegQuality = 92,
    ) {}

    public function enhance(SiteContext $site, ArticleImage $image, EntityManagerInterface $em): void
    {
        $image->setEnhancementStatus(ArticleImageEnhancementStatus::Processing);
        $image->setEnhancementError(null);
        $em->flush();

        $sourceUrl = $image->getSourceUrl() ?? $image->getUrl();

        try {
            $tmpDir = sys_get_temp_dir().'/mymedia-image-enhance';
            if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
                throw new \RuntimeException('No se pudo crear el directorio temporal.');
            }

            $tmpSource = $tmpDir.'/source-'.uniqid('', true);
            $this->downloadToFile($sourceUrl, $tmpSource);

            $dimensions = $this->metadataReader->readFromFile($tmpSource);
            $width = $dimensions['width'];
            $height = $dimensions['height'];
            $sortOrder = $image->getSortOrder();

            $image->setWidth($width);
            $image->setHeight($height);

            if ($this->qualityPolicy->meetsRequirements($width, $height, $sortOrder)) {
                $image->setEnhancementStatus(ArticleImageEnhancementStatus::Skipped);
                $image->setEnhancedAt(new \DateTimeImmutable());
                $em->flush();
                @unlink($tmpSource);

                return;
            }

            $target = $this->qualityPolicy->targetDimensions($width, $height, $sortOrder);
            $article = $image->getArticle();
            $articleId = $article->getId();
            if ($articleId === null) {
                throw new \RuntimeException('El artículo aún no tiene ID asignado.');
            }

            $imageId = $image->getId();
            if ($imageId === null) {
                throw new \RuntimeException('La imagen aún no tiene ID asignado.');
            }

            $relativeDir = sprintf('media/sites/%s/articles/%d', $site->code, $articleId);
            $absoluteDir = $this->projectDir.'/public/'.$relativeDir;
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
                throw new \RuntimeException('No se pudo crear el directorio de medios.');
            }

            $filename = sprintf('img-%d-enhanced.jpg', $imageId);
            $absoluteDest = $absoluteDir.'/'.$filename;
            $publicPath = '/'.$relativeDir.'/'.$filename;

            if ($this->enhancerChain->hasExternalEnhancer()) {
                $this->enhanceViaExternalService(
                    $sourceUrl,
                    $absoluteDest,
                    $target['width'],
                    $target['height'],
                );
            } else {
                $this->enhancerChain->enhance(
                    $tmpSource,
                    $absoluteDest,
                    $target['width'],
                    $target['height'],
                    $this->jpegQuality,
                );
            }

            $finalDimensions = $this->metadataReader->readFromFile($absoluteDest);

            if ($image->getSourceUrl() === null && !str_starts_with($sourceUrl, '/media/')) {
                $image->setSourceUrl($sourceUrl);
            }

            $image->setUrl($publicPath);
            $image->setWidth($finalDimensions['width']);
            $image->setHeight($finalDimensions['height']);
            $image->setEnhancementStatus(ArticleImageEnhancementStatus::Enhanced);
            $image->setEnhancedAt(new \DateTimeImmutable());
            $image->setEnhancementError(null);
            $em->flush();

            @unlink($tmpSource);
        } catch (\Throwable $e) {
            $image->setEnhancementStatus(ArticleImageEnhancementStatus::Failed);
            $image->setEnhancementError($e->getMessage());
            $em->flush();

            throw $e;
        }
    }

    private function downloadToFile(string $url, string $destination): void
    {
        if (str_starts_with($url, '/')) {
            $localPath = $this->projectDir.'/public'.str_replace('/', DIRECTORY_SEPARATOR, $url);
            if (!is_file($localPath)) {
                throw new \RuntimeException('Archivo local no encontrado: '.$url);
            }
            if (!copy($localPath, $destination)) {
                throw new \RuntimeException('No se pudo copiar el archivo local.');
            }

            return;
        }

        $response = $this->httpClient->request('GET', $url, [
            'max_duration' => 45,
            'headers' => [
                'Accept' => 'image/*',
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new \RuntimeException(sprintf('HTTP %d al descargar la imagen.', $status));
        }

        $content = '';
        foreach ($this->httpClient->stream($response) as $chunk) {
            if ($chunk->isTimeout()) {
                continue;
            }
            $content .= $chunk->getContent();
            if (strlen($content) > self::MAX_DOWNLOAD_BYTES) {
                throw new \RuntimeException('La imagen supera el tamaño máximo permitido.');
            }
        }

        if ($content === '') {
            throw new \RuntimeException('La imagen descargada está vacía.');
        }

        if (file_put_contents($destination, $content) === false) {
            throw new FileException('No se pudo guardar la imagen temporal.');
        }
    }

    private function enhanceViaExternalService(string $sourceUrl, string $destinationPath, int $targetWidth, int $targetHeight): void
    {
        $endpoint = $this->enhancerChain->getExternalEnhancerUrl();
        if ($endpoint === null) {
            throw new \RuntimeException('Servicio externo de mejora no configurado.');
        }

        $response = $this->httpClient->request('POST', $endpoint, [
            'json' => [
                'url' => $sourceUrl,
                'target_width' => $targetWidth,
                'target_height' => $targetHeight,
                'jpeg_quality' => $this->jpegQuality,
            ],
            'timeout' => 120,
        ]);

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new \RuntimeException(sprintf('Servicio de mejora respondió HTTP %d.', $status));
        }

        $content = $response->getContent();
        if ($content === '') {
            throw new \RuntimeException('El servicio de mejora devolvió una imagen vacía.');
        }

        if (file_put_contents($destinationPath, $content) === false) {
            throw new FileException('No se pudo guardar la imagen mejorada.');
        }
    }
}
