<?php

namespace App\Editorial;

use App\Entity\Site\ArticleImage;

/**
 * Resuelve atributos SEO de imágenes (alt, title, dimensiones).
 */
final class SeoImageAttributes
{
    /**
     * @return array{
     *     alt: string,
     *     title: string,
     *     width: ?int,
     *     height: ?int
     * }
     */
    public function forArticleImage(?ArticleImage $image, string $fallbackTitle): array
    {
        $fallback = $this->cleanText($fallbackTitle) ?: 'Imagen';
        if ($image === null) {
            return [
                'alt' => $fallback,
                'title' => $fallback,
                'width' => null,
                'height' => null,
            ];
        }

        $alt = $this->cleanText((string) ($image->getAlt() ?? ''));
        if ($alt === '') {
            $media = $image->getMedia();
            $alt = $this->cleanText((string) ($media?->getAlt() ?? ''));
        }
        if ($alt === '') {
            $alt = $fallback;
        }

        $title = $this->cleanText((string) ($image->getCaption() ?? ''));
        if ($title === '') {
            $title = $alt;
        }

        return [
            'alt' => $alt,
            'title' => $title,
            'width' => $image->getWidth(),
            'height' => $image->getHeight(),
        ];
    }

    /**
     * Completa alt/title vacíos en <img> del HTML editorial.
     */
    public function enrichHtml(string $html, string $fallbackTitle): string
    {
        $html = trim($html);
        if ($html === '' || !str_contains(strtolower($html), '<img')) {
            return $html;
        }

        $fallback = $this->cleanText($fallbackTitle) ?: 'Imagen';
        if (!class_exists(\DOMDocument::class)) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="seo-img-root">'.$html.'</div>';
        $loaded = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return $html;
        }

        $root = $dom->getElementById('seo-img-root');
        if ($root === null) {
            return $html;
        }

        /** @var \DOMElement $img */
        foreach ($root->getElementsByTagName('img') as $img) {
            $alt = $this->cleanText($img->getAttribute('alt'));
            if ($alt === '') {
                $alt = $fallback;
                $img->setAttribute('alt', $alt);
            }

            $title = $this->cleanText($img->getAttribute('title'));
            if ($title === '') {
                $img->setAttribute('title', $alt);
            }

            if (!$img->hasAttribute('decoding')) {
                $img->setAttribute('decoding', 'async');
            }
            if (!$img->hasAttribute('loading')) {
                $img->setAttribute('loading', 'lazy');
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out !== '' ? $out : $html;
    }

    private function cleanText(string $value): string
    {
        $value = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return mb_substr($value, 0, 200);
    }
}
