<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\Article;
use App\Editorial\Pdf\ArticleHtmlToTypstConverter;
use App\Editorial\Pdf\EditionPdfImageStore;
use App\Editorial\Pdf\EditionTypstDocumentBuilder;
use App\Editorial\Pdf\TypstCompiler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class EditionNewspaperPdfGenerator
{
    private const MAX_ARTICLES = 24;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $projectDir,
        private readonly EditionPdfImageStore $imageStore,
        private readonly ArticleHtmlToTypstConverter $htmlToTypst,
        private readonly EditionTypstDocumentBuilder $documentBuilder,
        private readonly TypstCompiler $typstCompiler,
    ) {}

    /**
     * @param list<Article> $articles
     */
    public function generate(
        SiteContext $site,
        array $theme,
        \DateTimeImmutable $editionDate,
        array $articles,
    ): string {
        if (!$this->typstCompiler->isAvailable()) {
            throw new \RuntimeException('Typst no está instalado en el servidor. Contacta con el administrador.');
        }

        $siteBaseUrl = rtrim($this->urlGenerator->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/');
        $editionUrl = $this->urlGenerator->generate(
            'public_edition_date',
            ['date' => $editionDate->format('Y-m-d')],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $editionDateLabel = $editionDate->format('d/m/Y');
        $limited = array_slice($articles, 0, self::MAX_ARTICLES);
        usort(
            $limited,
            static function (Article $a, Article $b): int {
                $byViews = $b->getViewCount() <=> $a->getViewCount();
                if ($byViews !== 0) {
                    return $byViews;
                }

                $aTime = $a->getPublishedAt()?->getTimestamp() ?? 0;
                $bTime = $b->getPublishedAt()?->getTimestamp() ?? 0;

                return $bTime <=> $aTime;
            },
        );

        $token = bin2hex(random_bytes(8));
        $cacheDir = $this->projectDir.'/var/cache/typst-edition';
        $workDir = $this->imageStore->prepareWorkDir($cacheDir, $token);
        $sourcePath = $workDir.'/edition.typ';
        $outputPath = $workDir.'/edition.pdf';

        $resolveImage = fn (string $src): ?string => $this->imageStore->store($src, $workDir, $siteBaseUrl);

        $rows = [];
        foreach ($limited as $article) {
            $rows[] = $this->mapArticle($article, $resolveImage);
        }

        $logoPath = null;
        $logoUrl = (string) ($theme['logoUrl'] ?? '');
        if ($logoUrl !== '') {
            $logoPath = $resolveImage($logoUrl);
        }

        $typst = $this->documentBuilder->build(
            siteName: $site->name,
            editionDateLabel: $editionDateLabel,
            editionUrl: $editionUrl,
            siteUrl: $siteBaseUrl,
            primaryHex: (string) ($theme['primaryColor'] ?? '#b91c1c'),
            logoPath: $logoPath,
            articles: $rows,
            totalArticles: count($articles),
            truncated: count($articles) > count($limited),
        );

        file_put_contents($sourcePath, $typst);

        $this->typstCompiler->compile($sourcePath, $outputPath, $workDir);

        $pdf = file_get_contents($outputPath);
        if ($pdf === false) {
            throw new \RuntimeException('No se pudo leer el PDF generado.');
        }

        return $pdf;
    }

    /**
     * @param callable(string): ?string $resolveImage
     *
     * @return array{
     *     title: string,
     *     category: ?string,
     *     publishedAt: ?string,
     *     subtitle: ?string,
     *     lead: ?string,
     *     bodyTypst: string,
     *     heroImage: ?string,
     *     viewCount: int,
     *     gallery: list<array{path: string, caption: ?string}>
     * }
     */
    private function mapArticle(Article $article, callable $resolveImage): array
    {
        $category = $article->getCategory();
        $publishedAt = $article->getPublishedAt();

        $heroUrl = $article->getFeaturedImage()?->getUrl() ?? '';
        $heroImage = $heroUrl !== '' ? $resolveImage($heroUrl) : null;

        $gallery = [];
        $seenUrls = [];
        if ($heroUrl !== '') {
            $seenUrls[$heroUrl] = true;
        }

        foreach ($article->getImages() as $image) {
            $url = $image->getUrl();
            if ($url === '' || isset($seenUrls[$url])) {
                continue;
            }
            $path = $resolveImage($url);
            if ($path === null) {
                continue;
            }
            $seenUrls[$url] = true;
            $gallery[] = [
                'path' => $path,
                'caption' => $image->getCaption(),
            ];
        }

        $lead = trim($article->getLead() ?? '');
        if ($lead === '') {
            $lead = trim($article->getSubtitle() ?? '');
        }

        return [
            'title' => $article->getTitle(),
            'category' => $category?->getName(),
            'publishedAt' => $publishedAt?->format('H:i'),
            'subtitle' => trim($article->getSubtitle() ?? '') !== '' ? trim($article->getSubtitle() ?? '') : null,
            'lead' => $lead !== '' ? $lead : null,
            'bodyTypst' => $this->htmlToTypst->convert($article->getBodyHtml(), $resolveImage),
            'heroImage' => $heroImage,
            'viewCount' => $article->getViewCount(),
            'gallery' => $gallery,
        ];
    }
}
