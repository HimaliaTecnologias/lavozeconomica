<?php

namespace App\Editorial;

use App\Entity\Site\ArticleImage;
use App\Entity\Site\Media;
use Doctrine\ORM\EntityManagerInterface;

final class MediaLinkedArticleSyncService
{
    public function syncFromMedia(Media $media, EntityManagerInterface $em): int
    {
        $images = $em->getRepository(ArticleImage::class)->findBy(['media' => $media]);
        foreach ($images as $image) {
            $image->applyFromLibraryMedia($media);
        }

        return count($images);
    }
}
