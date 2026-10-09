<?php

namespace App\Editorial;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ArticleImageMetadataReader
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * @return array{width: int, height: int}
     */
    public function readFromFile(string $path): array
    {
        $info = @getimagesize($path);
        if ($info === false || !isset($info[0], $info[1])) {
            throw new \RuntimeException('No se pudieron leer las dimensiones de la imagen.');
        }

        return [
            'width' => (int) $info[0],
            'height' => (int) $info[1],
        ];
    }

    /**
     * @return array{width: int, height: int}
     */
    public function readFromUrl(string $url): array
    {
        $response = $this->httpClient->request('GET', $url, [
            'max_duration' => 30,
            'headers' => [
                'Accept' => 'image/*',
            ],
        ]);

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new \RuntimeException(sprintf('HTTP %d al descargar la imagen.', $status));
        }

        $content = $response->getContent(false);
        if ($content === '') {
            throw new \RuntimeException('La imagen descargada está vacía.');
        }

        $info = @getimagesizefromstring($content);
        if ($info === false || !isset($info[0], $info[1])) {
            throw new \RuntimeException('El contenido descargado no es una imagen válida.');
        }

        return [
            'width' => (int) $info[0],
            'height' => (int) $info[1],
        ];
    }
}
