<?php

namespace App\Editorial;

final class ArticleImageUrlExtractor
{
    /**
     * @param array<string, mixed> $raw
     *
     * @return list<array{url: string, alt: ?string, caption: ?string}>
     */
    public function fromRawPayload(array $raw): array
    {
        $items = [];
        $seen = [];

        $title = isset($raw['titular']) ? trim((string) $raw['titular']) : null;
        if ($title === '') {
            $title = isset($raw['title']) ? trim((string) $raw['title']) : null;
        }

        $this->addUrl($items, $seen, $raw['imagen'] ?? $raw['image'] ?? $raw['main_image'] ?? null, $title, null);
        $this->addUrl($items, $seen, $raw['imagen_principal'] ?? null, $title, null);

        foreach (['imagenes', 'images', 'galeria', 'gallery', 'fotos', 'photos'] as $key) {
            if (!isset($raw[$key]) || !is_array($raw[$key])) {
                continue;
            }

            foreach ($raw[$key] as $entry) {
                if (is_string($entry)) {
                    $this->addUrl($items, $seen, $entry, $title, null);
                    continue;
                }

                if (!is_array($entry)) {
                    continue;
                }

                $url = $entry['url'] ?? $entry['src'] ?? $entry['imagen'] ?? $entry['image'] ?? null;
                $alt = $entry['alt'] ?? $entry['titulo'] ?? $entry['title'] ?? $title;
                $caption = $entry['caption'] ?? $entry['pie'] ?? $entry['descripcion'] ?? null;
                $this->addUrl($items, $seen, $url, is_string($alt) ? $alt : null, is_string($caption) ? $caption : null);
            }
        }

        return $items;
    }

    /**
     * @param list<array{url: string, alt: ?string, caption: ?string}> $items
     * @param array<string, true> $seen
     */
    private function addUrl(array &$items, array &$seen, mixed $url, ?string $alt, ?string $caption): void
    {
        if (!is_string($url)) {
            return;
        }

        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        if (isset($seen[$url])) {
            return;
        }

        $seen[$url] = true;
        $items[] = [
            'url' => $url,
            'alt' => $alt !== null && trim($alt) !== '' ? trim($alt) : null,
            'caption' => $caption !== null && trim($caption) !== '' ? trim($caption) : null,
        ];
    }
}
