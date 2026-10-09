<?php

namespace App\Editorial\Pdf;

final class EditionTypstDocumentBuilder
{
    private const COVER_THUMB_MAX = 6;
    private const BODY_IMAGE_WIDTH = '100%';

    /**
     * @param list<array{
     *     title: string,
     *     category: ?string,
     *     publishedAt: ?string,
     *     subtitle: ?string,
     *     lead: ?string,
     *     bodyTypst: string,
     *     heroImage: ?string,
     *     viewCount: int,
     *     gallery: list<array{path: string, caption: ?string}>
     * }> $articles
     */
    public function build(
        string $siteName,
        string $editionDateLabel,
        string $editionUrl,
        string $siteUrl,
        string $primaryHex,
        ?string $logoPath,
        array $articles,
        int $totalArticles,
        bool $truncated,
    ): string {
        $primary = $this->hexToTypstRgb($primaryHex);
        $displayUrl = $this->publicSiteLabel($siteUrl);

        $lines = [];
        $lines[] = '#let primary = rgb("'.$primary.'")';
        $lines[] = '#let ink = rgb("111111")';
        $lines[] = '#let ink_soft = rgb("333333")';
        $lines[] = '#let ink_muted = rgb("666666")';
        $lines[] = '#let surface = rgb("f4f4f4")';
        $lines[] = '#let rule_color = rgb("d4d4d4")';
        $lines[] = '#set page(';
        $lines[] = '  paper: "a4",';
        $lines[] = '  margin: (left: 1.35cm, right: 1.35cm, top: 1.35cm, bottom: 1.7cm),';
        $lines[] = '  header: context [';
        $lines[] = '    #align(center)[#text(size: 7pt, fill: ink_muted)['.TypstEscaper::content($siteName).' · '.TypstEscaper::content($editionDateLabel).']]';
        $lines[] = '  ],';
        $lines[] = '  footer: context [';
        $lines[] = '    #align(center)[#text(size: 7.5pt, fill: ink_muted)[#counter(page).display() · '.TypstEscaper::content($displayUrl).']]';
        $lines[] = '  ],';
        $lines[] = ')';
        $lines[] = '#set par(leading: 0.78em, justify: true)';
        $lines[] = '#set text(lang: "es", font: "Libertinus Serif", size: 10pt)';
        $lines[] = '';

        if ($articles === []) {
            $lines[] = $this->masthead($siteName, $editionDateLabel, $displayUrl, $logoPath);
            $lines[] = '#v(0.8cm)';
            $lines[] = '#align(center)[#block(width: 100%, inset: 24pt, stroke: 0.75pt + rule_color, fill: surface)[#text(fill: ink_muted)[No hay noticias publicadas en esta fecha.]]]';
        } else {
            $lines[] = $this->buildCoverPage($siteName, $editionDateLabel, $displayUrl, $logoPath, $articles);
            $lines[] = $this->buildBodyPages($articles);
        }

        $lines[] = '#v(0.55cm)';
        $lines[] = '#align(center)[#line(length: 40%, stroke: 0.6pt + rule_color)]';
        $lines[] = '#v(0.25cm)';
        $summary = TypstEscaper::content($siteName).' — Edición '.TypstEscaper::content($editionDateLabel);
        if ($truncated) {
            $summary .= ' · '.$totalArticles.' noticias (mostrando '.count($articles).')';
        } else {
            $summary .= ' · '.$totalArticles.' noticia'.($totalArticles !== 1 ? 's' : '');
        }
        $lines[] = '#align(center)[#text(size: 7.5pt, fill: ink_muted)['.$summary.']]';
        $lines[] = '#align(center)[#text(size: 7pt, fill: ink_muted)['.TypstEscaper::content($editionUrl).']]';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param list<array{
     *     title: string,
     *     category: ?string,
     *     publishedAt: ?string,
     *     subtitle: ?string,
     *     lead: ?string,
     *     bodyTypst: string,
     *     heroImage: ?string,
     *     viewCount: int,
     *     gallery: list<array{path: string, caption: ?string}>
     * }> $articles
     */
    private function buildCoverPage(
        string $siteName,
        string $editionDateLabel,
        string $displayUrl,
        ?string $logoPath,
        array $articles,
    ): string {
        $hero = $articles[0];
        $thumbs = array_slice($articles, 1, self::COVER_THUMB_MAX);

        $parts = [];
        $parts[] = $this->masthead($siteName, $editionDateLabel, $displayUrl, $logoPath);
        $parts[] = '#v(0.4cm)';
        $parts[] = $this->coverHero($hero);
        if ($thumbs !== []) {
            $parts[] = '#v(0.5cm)';
            $parts[] = '#text(size: 8pt, weight: "bold", tracking: 0.14em, fill: ink_muted)[EN LA EDICIÓN DE HOY]';
            $parts[] = '#v(0.3cm)';
            $parts[] = $this->coverThumbGrid($thumbs);
        }

        return implode("\n", $parts);
    }

    /**
     * @param array{
     *     title: string,
     *     category: ?string,
     *     publishedAt: ?string,
     *     subtitle: ?string,
     *     lead: ?string,
     *     bodyTypst: string,
     *     heroImage: ?string,
     *     viewCount: int,
     *     gallery: list<array{path: string, caption: ?string}>
     * } $hero
     */
    private function coverHero(array $hero): string
    {
        $badge = '#box(fill: primary, inset: (x: 7pt, y: 3pt), radius: 2pt)[#text(size: 7pt, weight: "bold", fill: white, tracking: 0.08em)[LO MÁS LEÍDO · '.$hero['viewCount'].' LECTURAS]]';
        $title = '#text(size: 18pt, weight: "bold", fill: ink)['.TypstEscaper::content($hero['title']).']';

        $meta = '';
        if ($hero['publishedAt'] !== null) {
            $meta = '#v(0.25em)#text(size: 8pt, fill: ink_muted)['.TypstEscaper::content($hero['publishedAt']).']';
        }

        $leadBlock = '';
        if ($hero['lead'] !== null && $hero['lead'] !== '') {
            $lead = $hero['lead'];
            if (mb_strlen($lead) > 240) {
                $lead = mb_substr($lead, 0, 237).'…';
            }
            $leadBlock = '#v(0.4em)#text(size: 10pt, fill: ink_soft)['.TypstEscaper::content($lead).']';
        }

        $copy = $badge.'#v(0.35em)'.$this->categoryRibbon($hero['category']).$title.$meta.$leadBlock;

        if ($hero['heroImage'] !== null) {
            return '#grid(
  columns: (1.05fr, 0.95fr),
  column-gutter: 12pt,
  align: (top, top),
  [#block(width: 100%, clip: true, radius: 3pt, stroke: 0.6pt + rule_color)[
    #image("'.TypstEscaper::path($hero['heroImage']).'", width: 100%, height: 7.2cm, fit: "cover")
  ]],
  [#block(width: 100%)[
    '.$copy.'
  ]],
)';
        }

        return '#block(width: 100%, inset: 14pt, stroke: 0.6pt + rule_color, fill: surface, radius: 3pt)[
  '.$copy.'
]';
    }

    /**
     * @param list<array{
     *     title: string,
     *     category: ?string,
     *     publishedAt: ?string,
     *     subtitle: ?string,
     *     lead: ?string,
     *     bodyTypst: string,
     *     heroImage: ?string,
     *     viewCount: int,
     *     gallery: list<array{path: string, caption: ?string}>
     * }> $thumbs
     */
    private function coverThumbGrid(array $thumbs): string
    {
        $colCount = min(3, max(1, count($thumbs)));
        $cells = [];
        foreach ($thumbs as $article) {
            $cells[] = '['.$this->coverThumbCell($article).']';
        }

        return '#grid(
  columns: ('.implode(', ', array_fill(0, $colCount, '1fr')).'),
  column-gutter: 9pt,
  row-gutter: 9pt,
  '.implode(",\n  ", $cells).',
)';
    }

    /**
     * @param array{
     *     title: string,
     *     category: ?string,
     *     publishedAt: ?string,
     *     subtitle: ?string,
     *     lead: ?string,
     *     bodyTypst: string,
     *     heroImage: ?string,
     *     viewCount: int,
     *     gallery: list<array{path: string, caption: ?string}>
     * } $article
     */
    private function coverThumbCell(array $article): string
    {
        $title = TypstEscaper::content($article['title']);
        $cat = $this->categoryRibbon($article['category'], small: true);
        $views = $article['viewCount'] > 0
            ? '#text(size: 7pt, fill: ink_muted)['.$article['viewCount'].' lecturas]'
            : '';

        if ($article['heroImage'] !== null) {
            return '#block(width: 100%, clip: true, stroke: 0.5pt + rule_color, radius: 2pt)[
  #image("'.TypstEscaper::path($article['heroImage']).'", width: 100%, height: 2.15cm, fit: "cover")
  #block(inset: 7pt)[
    '.$cat.'
    #text(size: 8.5pt, weight: "bold", fill: ink)['.$title.']
    '.($views !== '' ? '#v(0.15em)'.$views : '').'
  ]
]';
        }

        return '#block(width: 100%, inset: 8pt, stroke: 0.5pt + rule_color, radius: 2pt, fill: surface)[
  '.$cat.'
  #text(size: 9pt, weight: "bold", fill: ink)['.$title.']
  '.($views !== '' ? '#v(0.2em)'.$views : '').'
]';
    }

    /**
     * @param list<array{
     *     title: string,
     *     category: ?string,
     *     publishedAt: ?string,
     *     subtitle: ?string,
     *     lead: ?string,
     *     bodyTypst: string,
     *     heroImage: ?string,
     *     viewCount: int,
     *     gallery: list<array{path: string, caption: ?string}>
     * }> $articles
     */
    private function buildBodyPages(array $articles): string
    {
        $blocks = [];
        foreach ($articles as $index => $article) {
            $blocks[] = $this->bodyArticleBlock($article, $index + 1);
        }

        return '#pagebreak()
#align(left)[#text(size: 9pt, weight: "bold", tracking: 0.12em, fill: primary)[CONTENIDO COMPLETO · EDICIÓN DEL DÍA]]
#v(0.35cm)
#columns(2, gutter: 0.95cm)[
'.implode("\n", $blocks).'
]';
    }

    /**
     * @param array{
     *     title: string,
     *     category: ?string,
     *     publishedAt: ?string,
     *     subtitle: ?string,
     *     lead: ?string,
     *     bodyTypst: string,
     *     heroImage: ?string,
     *     viewCount: int,
     *     gallery: list<array{path: string, caption: ?string}>
     * } $article
     */
    private function bodyArticleBlock(array $article, int $number): string
    {
        $num = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
        $metaParts = [];
        if ($article['publishedAt'] !== null) {
            $metaParts[] = TypstEscaper::content($article['publishedAt']);
        }
        if ($article['viewCount'] > 0) {
            $metaParts[] = $article['viewCount'].' lecturas';
        }
        $meta = $metaParts !== [] ? '#text(size: 7.5pt, fill: ink_muted)['.implode(' · ', $metaParts).']' : '';

        $leadBlock = '';
        if ($article['lead'] !== null && $article['lead'] !== '') {
            $leadBlock = '#v(0.25em)#text(size: 9.5pt, weight: "bold", fill: ink_soft)['.TypstEscaper::content($article['lead']).']';
        }

        $imageBlock = '';
        if ($article['heroImage'] !== null) {
            $imageBlock = '#v(0.3em)#block(width: '.self::BODY_IMAGE_WIDTH.', clip: true, radius: 2pt, stroke: 0.4pt + rule_color)[
  #image("'.TypstEscaper::path($article['heroImage']).'", width: 100%)
]';
        }

        $body = $article['bodyTypst'] !== '' ? '#v(0.3em)'.$article['bodyTypst'] : '';
        $gallery = $this->galleryBlock($article['gallery']);

        return '#block(breakable: true, above: 0.45cm, below: 0.2cm)[
  #grid(
    columns: (auto, 1fr),
    column-gutter: 7pt,
    align: (top, top),
    [#box(fill: primary, inset: (x: 5pt, y: 3pt), radius: 2pt)[#text(fill: white, size: 8.5pt, weight: "bold")['.$num.']]],
    [
      '.$this->categoryRibbon($article['category'], small: true).'
      #text(size: 11.5pt, weight: "bold", fill: ink)['.TypstEscaper::content($article['title']).']
      '.($meta !== '' ? '#v(0.12em)'.$meta : '').'
    ],
  )
  '.$leadBlock.'
  '.$imageBlock.'
  '.$body.'
  '.$gallery.'
  #v(0.3em)
  #line(length: 100%, stroke: 0.35pt + rule_color)
]';
    }

    private function masthead(
        string $siteName,
        string $editionDateLabel,
        string $displayUrl,
        ?string $logoPath,
    ): string {
        $logoCell = '[]';
        if ($logoPath !== null) {
            $logoCell = '[#image("'.TypstEscaper::path($logoPath).'", height: 0.9cm)]';
        }

        return '#grid(
  columns: (auto, 1fr, auto),
  column-gutter: 10pt,
  align: (left + horizon, center + horizon, right + horizon),
  '.$logoCell.',
  [#block(width: 100%, fill: ink, inset: (y: 9pt, x: 12pt), radius: 2pt)[
    #align(center)[
      #text(fill: white, size: 20pt, weight: "bold", tracking: 0.02em)['.TypstEscaper::content($siteName).']
      #v(2pt)
      #text(fill: white.transparentize(25%), size: 8pt, tracking: 0.14em)[EDICIÓN DEL DÍA · '.TypstEscaper::content($editionDateLabel).']
    ]
  ]],
  [#text(size: 7pt, fill: ink_muted)['.TypstEscaper::content($displayUrl).']],
)';
    }

    private function categoryRibbon(?string $category, bool $small = false): string
    {
        if ($category === null || $category === '') {
            return '';
        }

        $size = $small ? '6.5pt' : '7pt';
        $pad = $small ? '(x: 5pt, y: 1.5pt)' : '(x: 6pt, y: 2pt)';

        return '#box(fill: primary, inset: '.$pad.', radius: 1pt)[#text(size: '.$size.', weight: "bold", fill: white, tracking: 0.1em)['.TypstEscaper::content(mb_strtoupper($category)).']]#v(0.25em)';
    }

    /**
     * @param list<array{path: string, caption: ?string}> $gallery
     */
    private function galleryBlock(array $gallery): string
    {
        if ($gallery === []) {
            return '';
        }

        $cells = [];
        foreach (array_slice($gallery, 0, 4) as $item) {
            $caption = $item['caption'] !== null && $item['caption'] !== ''
                ? '#v(0.12em)#text(size: 7pt, fill: ink_muted, style: "italic")['.TypstEscaper::content($item['caption']).']'
                : '';
            $cells[] = '[#block(width: 100%, clip: true, stroke: 0.4pt + rule_color, radius: 2pt)[
  #image("'.TypstEscaper::path($item['path']).'", width: 100%)
  '.$caption.'
]]';
        }

        return '#v(0.35em)#text(size: 7.5pt, weight: "bold", fill: ink_muted, tracking: 0.08em)[MÁS FOTOS]#v(0.2em)#grid(
  columns: (1fr, 1fr),
  column-gutter: 6pt,
  row-gutter: 6pt,
  '.implode(",\n  ", $cells).',
)';
    }

    private function publicSiteLabel(string $siteUrl): string
    {
        $host = parse_url($siteUrl, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || $host === '127.0.0.1' || $host === 'localhost') {
            return 'Edición digital';
        }

        return $host;
    }

    private function hexToTypstRgb(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return 'b91c1c';
        }

        return strtolower($hex);
    }
}
