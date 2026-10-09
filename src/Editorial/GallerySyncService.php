<?php

namespace App\Editorial;

use App\Entity\Site\Gallery;
use App\Entity\Site\GalleryImage;
use App\Entity\Site\Media;
use Doctrine\ORM\EntityManagerInterface;

final class GallerySyncService
{
    /**
     * @param list<array{url?: string, alt?: string, caption?: string, mediaId?: string|int}> $rows
     */
    public function createGallery(string $title, array $rows, EntityManagerInterface $em): Gallery
    {
        $gallery = new Gallery($title);
        $this->syncImages($em, $gallery, $rows);
        if ($gallery->getImages()->isEmpty()) {
            throw new \InvalidArgumentException('La galería necesita al menos una imagen.');
        }
        $em->persist($gallery);

        return $gallery;
    }

    /**
     * @param list<array{url?: string, alt?: string, caption?: string, mediaId?: string|int}> $rows
     */
    public function syncGallery(Gallery $gallery, string $title, array $rows, EntityManagerInterface $em): void
    {
        $gallery->setTitle($title);
        $this->syncImages($em, $gallery, $rows);
        if ($gallery->getImages()->isEmpty()) {
            throw new \InvalidArgumentException('La galería necesita al menos una imagen.');
        }
        $gallery->touch();
    }

    /**
     * @param list<array{url?: string, alt?: string, caption?: string, mediaId?: string|int}> $rows
     */
    private function syncImages(EntityManagerInterface $em, Gallery $gallery, array $rows): void
    {
        $gallery->clearImages();

        $sort = 0;
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $mediaId = trim((string) ($row['mediaId'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));

            if ($mediaId !== '') {
                $media = $em->find(Media::class, (int) $mediaId);
                if ($media !== null) {
                    $image = new GalleryImage($gallery, $media->getPublicPath(), $sort++);
                    $image->applyFromLibraryMedia($media);
                    $alt = trim((string) ($row['alt'] ?? ''));
                    $caption = trim((string) ($row['caption'] ?? ''));
                    if ($alt !== '') {
                        $image->setAlt($alt);
                    }
                    if ($caption !== '') {
                        $image->setCaption($caption);
                    }
                    $gallery->addImage($image);
                    $em->persist($image);

                    continue;
                }
            }

            if ($url === '' || !$this->isAllowedImageReference($url)) {
                continue;
            }

            $image = new GalleryImage($gallery, $url, $sort++);
            $alt = trim((string) ($row['alt'] ?? ''));
            $caption = trim((string) ($row['caption'] ?? ''));
            $image->setAlt($alt !== '' ? $alt : null);
            $image->setCaption($caption !== '' ? $caption : null);
            $gallery->addImage($image);
            $em->persist($image);
        }
    }

    private function isAllowedImageReference(string $url): bool
    {
        if (str_starts_with($url, '/media/')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
