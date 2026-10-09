<?php

namespace App\Editorial;

use App\Entity\Site\Article;
use App\Entity\Site\ArticleImage;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Entity\Site\Media;
use Doctrine\ORM\EntityManagerInterface;

final class ArticleImageSyncService
{
    public function __construct(
        private readonly GallerySyncService $gallerySync,
    ) {}

    /**
     * Imagen principal (portada). Sustituye todas las ArticleImage del artículo.
     *
     * @param array{url?: string, alt?: string, caption?: string, mediaId?: string|int}|null $row
     */
    public function syncFeaturedFromForm(EntityManagerInterface $em, Article $article, ?array $row): void
    {
        $article->clearImages();

        if ($row === null || !\is_array($row)) {
            return;
        }

        $this->persistImageRow($em, $article, $row, 0, true);
    }

    /**
     * @deprecated Prefer syncFeaturedFromForm + Gallery; kept for multi-row legacy forms.
     *
     * @param list<array{url?: string, alt?: string, caption?: string, mediaId?: string|int}> $rows
     */
    public function syncFromFormRows(EntityManagerInterface $em, Article $article, array $rows): void
    {
        $article->clearImages();

        $sort = 0;
        foreach ($rows as $row) {
            if (!\is_array($row) || !$this->rowHasImage($em, $row)) {
                continue;
            }
            $this->persistImageRow($em, $article, $row, $sort, $sort === 0);
            ++$sort;
        }
    }

    /**
     * @param list<array{url: string, alt: ?string, caption: ?string}> $extracted
     */
    public function syncFromExtracted(EntityManagerInterface $em, Article $article, array $extracted): void
    {
        if ($extracted === []) {
            return;
        }

        $article->clearImages();

        $first = $extracted[0];
        $featured = new ArticleImage($article, $first['url'], 0);
        $featured->setAlt($first['alt']);
        $featured->setCaption($first['caption']);
        $featured->setIsFeatured(true);
        $article->addImage($featured);
        $em->persist($featured);

        $rest = \array_slice($extracted, 1);
        if ($rest === []) {
            $article->setGallery(null);

            return;
        }

        $galleryRows = [];
        foreach ($rest as $item) {
            $galleryRows[] = [
                'url' => $item['url'],
                'alt' => $item['alt'] ?? '',
                'caption' => $item['caption'] ?? '',
            ];
        }

        $title = mb_substr('Galería: '.$article->getTitle(), 0, 255);
        try {
            $gallery = $this->gallerySync->createGallery($title, $galleryRows, $em);
            $article->setGallery($gallery);
        } catch (\InvalidArgumentException) {
            $article->setGallery(null);
        }
    }

    /**
     * @param array{url?: string, alt?: string, caption?: string, mediaId?: string|int} $row
     */
    private function persistImageRow(
        EntityManagerInterface $em,
        Article $article,
        array $row,
        int $sortOrder,
        bool $featured,
    ): void {
        $mediaId = trim((string) ($row['mediaId'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));

        if ($mediaId !== '') {
            $media = $em->find(Media::class, (int) $mediaId);
            if ($media !== null) {
                $this->persistFromMedia($em, $article, $media, $row, $sortOrder, $featured);

                return;
            }
        }

        if ($url === '' || !$this->isAllowedImageReference($url)) {
            return;
        }

        $image = new ArticleImage($article, $url, $sortOrder);
        $alt = trim((string) ($row['alt'] ?? ''));
        $caption = trim((string) ($row['caption'] ?? ''));
        $image->setAlt($alt !== '' ? $alt : null);
        $image->setCaption($caption !== '' ? $caption : null);
        $image->setIsFeatured($featured);
        $article->addImage($image);
        $em->persist($image);
    }

    /**
     * @param array{url?: string, alt?: string, caption?: string, mediaId?: string|int} $row
     */
    private function rowHasImage(EntityManagerInterface $em, array $row): bool
    {
        $mediaId = trim((string) ($row['mediaId'] ?? ''));
        if ($mediaId !== '' && $em->find(Media::class, (int) $mediaId) !== null) {
            return true;
        }

        $url = trim((string) ($row['url'] ?? ''));

        return $url !== '' && $this->isAllowedImageReference($url);
    }

    /**
     * @param array{alt?: string, caption?: string} $row
     */
    private function persistFromMedia(
        EntityManagerInterface $em,
        Article $article,
        Media $media,
        array $row,
        int $sortOrder,
        bool $featured = false,
    ): void {
        $image = new ArticleImage($article, $media->getPublicPath(), $sortOrder);
        $image->applyFromLibraryMedia($media);
        $image->setIsFeatured($featured);

        $alt = trim((string) ($row['alt'] ?? ''));
        $caption = trim((string) ($row['caption'] ?? ''));
        if ($alt !== '') {
            $image->setAlt($alt);
        }
        if ($caption !== '') {
            $image->setCaption($caption);
        }

        if ($media->getEnhancementStatus() === ArticleImageEnhancementStatus::Pending) {
            $image->setEnhancementStatus(ArticleImageEnhancementStatus::Pending);
        }

        $article->addImage($image);
        $em->persist($image);
    }

    private function isAllowedImageReference(string $url): bool
    {
        if (str_starts_with($url, '/media/')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
