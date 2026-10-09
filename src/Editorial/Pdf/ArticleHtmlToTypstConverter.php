<?php

namespace App\Editorial\Pdf;

final class ArticleHtmlToTypstConverter
{
    /**
     * @param callable(string): ?string $resolveImage returns relative path inside typst root
     */
    public function convert(string $html, callable $resolveImage): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>';
        $loaded = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $this->plainParagraphs(strip_tags($html));
        }

        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return $this->plainParagraphs(strip_tags($html));
        }

        $parts = [];
        foreach ($body->childNodes as $child) {
            $chunk = $this->renderNode($child, $resolveImage);
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        if ($parts === []) {
            return $this->plainParagraphs(strip_tags($html));
        }

        return implode("\n", $parts);
    }

  /**
   * @param callable(string): ?string $resolveImage
   */
    private function renderNode(\DOMNode $node, callable $resolveImage): string
    {
        if ($node instanceof \DOMText) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent ?? '') ?? '');
            return $text !== '' ? TypstEscaper::content($text) : '';
        }

        if (!$node instanceof \DOMElement) {
            return '';
        }

        $tag = strtolower($node->tagName);

        return match ($tag) {
            'p' => $this->renderBlock($node, $resolveImage, 'para'),
            'h1', 'h2', 'h3', 'h4' => $this->renderHeading($node, $resolveImage, $tag),
            'ul', 'ol' => $this->renderList($node, $resolveImage, $tag === 'ol'),
            'blockquote' => $this->renderQuote($node, $resolveImage),
            'figure' => $this->renderFigure($node, $resolveImage),
            'img' => $this->renderImageElement($node, $resolveImage),
            'div', 'section', 'article' => $this->renderChildren($node, $resolveImage),
            'br' => '#linebreak()',
            default => $this->renderChildren($node, $resolveImage),
        };
    }

    /**
     * @param callable(string): ?string $resolveImage
     */
    private function renderChildren(\DOMElement $node, callable $resolveImage): string
    {
        $parts = [];
        foreach ($node->childNodes as $child) {
            $chunk = $this->renderNode($child, $resolveImage);
            if ($chunk !== '') {
                $parts[] = $chunk;
            }
        }

        return implode('', $parts);
    }

    /**
     * @param callable(string): ?string $resolveImage
     */
    private function renderBlock(\DOMElement $node, callable $resolveImage, string $kind): string
    {
        $inner = $this->renderInlineChildren($node, $resolveImage);
        if ($inner === '') {
            return '';
        }

        if ($kind === 'para') {
            return '#block[
  #set par(leading: 0.72em, justify: true)
  #set text(size: 9.5pt, fill: ink_soft)
  '.$inner.'
]#v(0.4em)';
        }

        return $inner;
    }

    /**
     * @param callable(string): ?string $resolveImage
     */
    private function renderHeading(\DOMElement $node, callable $resolveImage, string $tag): string
    {
        $inner = $this->renderInlineChildren($node, $resolveImage);
        if ($inner === '') {
            return '';
        }

        $size = match ($tag) {
            'h1' => '16pt',
            'h2' => '14pt',
            'h3' => '12pt',
            default => '11pt',
        };

        return '#v(0.65em)#text(size: '.$size.', weight: "bold", fill: ink)['.$inner.']#v(0.35em)';
    }

    /**
     * @param callable(string): ?string $resolveImage
     */
    private function renderInlineChildren(\DOMElement $node, callable $resolveImage): string
    {
        $parts = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $text = $child->textContent ?? '';
                if ($text !== '') {
                    $parts[] = TypstEscaper::content($text);
                }
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if ($tag === 'br') {
                $parts[] = '#linebreak()';
                continue;
            }
            if ($tag === 'img') {
                $img = $this->renderImageElement($child, $resolveImage);
                if ($img !== '') {
                    $parts[] = $img;
                }
                continue;
            }
            $inner = $this->renderInlineChildren($child, $resolveImage);
            if ($inner === '') {
                continue;
            }
            $parts[] = match ($tag) {
                'strong', 'b' => '#text(weight: "bold")['.$inner.']',
                'em', 'i' => '#text(style: "italic")['.$inner.']',
                'a' => '#text(fill: primary)['.$inner.']',
                default => $inner,
            };
        }

        return implode('', $parts);
    }

    /**
     * @param callable(string): ?string $resolveImage
     */
    private function renderList(\DOMElement $node, callable $resolveImage, bool $ordered): string
    {
        $items = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement && strtolower($child->tagName) === 'li') {
                $item = $this->renderInlineChildren($child, $resolveImage);
                if ($item !== '') {
                    $items[] = $item;
                }
            }
        }
        if ($items === []) {
            return '';
        }

        $marker = $ordered ? '1.' : '•';
        $lines = [];
        foreach ($items as $item) {
            $lines[] = $marker.' '.$item;
        }

        return '#v(0.3em)#block[
  #set par(leading: 0.7em, justify: true)
  #set text(size: 9.5pt, fill: ink_soft)
  '.implode('#linebreak()', $lines).'
]#v(0.35em)';
    }

    /**
     * @param callable(string): ?string $resolveImage
     */
    private function renderQuote(\DOMElement $node, callable $resolveImage): string
    {
        $inner = $this->renderInlineChildren($node, $resolveImage);
        if ($inner === '') {
            return '';
        }

        return '#block(fill: surface, inset: 10pt, stroke: 0.5pt + rule_color, radius: 4pt)[#text(size: 10pt, style: "italic", fill: ink_soft)['.$inner.']]#v(0.5em)';
    }

    /**
     * @param callable(string): ?string $resolveImage
     */
    private function renderFigure(\DOMElement $node, callable $resolveImage): string
    {
        $img = null;
        $caption = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->tagName);
                if ($tag === 'img') {
                    $img = $this->renderImageElement($child, $resolveImage);
                } elseif ($tag === 'figcaption') {
                    $caption = trim($child->textContent ?? '');
                }
            }
        }

        if ($img === null || $img === '') {
            return $this->renderChildren($node, $resolveImage);
        }

        if ($caption !== '') {
            return $img.'#v(0.25em)#align(center)[#text(size: 8pt, fill: ink_muted)['.TypstEscaper::content($caption).']]#v(0.55em)';
        }

        return $img.'#v(0.55em)';
    }

    /**
     * @param callable(string): ?string $resolveImage
     */
    private function renderImageElement(\DOMElement $img, callable $resolveImage): string
    {
        $src = trim($img->getAttribute('src'));
        if ($src === '') {
            return '';
        }

        $path = $resolveImage($src);
        if ($path === null) {
            return '';
        }

        $alt = trim($img->getAttribute('alt'));

        return '#align(left)[#box(width: 100%, clip: true, radius: 2pt, stroke: 0.4pt + rule_color)[#image("'.TypstEscaper::path($path).'", width: 100%)]]'
            .($alt !== '' ? '#v(0.15em)#text(size: 7.5pt, fill: ink_muted)['.TypstEscaper::content($alt).']' : '')
            .'#v(0.35em)';
    }

    private function plainParagraphs(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }

        $chunks = preg_split('/(?:\r\n|\r|\n){2,}/', $text) ?: [];
        $parts = [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $parts[] = '#block[
  #set par(leading: 0.72em, justify: true)
  #set text(size: 9.5pt, fill: ink_soft)
  '.TypstEscaper::content($chunk).'
]#v(0.4em)';
        }

        return implode("\n", $parts);
    }
}
