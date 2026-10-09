<?php

namespace App\Editorial;

use App\Entity\Site\Media;

final class MediaJsonSerializer
{
    public function toArray(Media $media): array
    {
        return [
            'id' => $media->getId(),
            'url' => $media->getPublicPath(),
            'thumbnail' => $media->getPublicPath(),
            'originalFilename' => $media->getOriginalFilename(),
            'title' => $media->getTitle(),
            'alt' => $media->getAlt(),
            'caption' => $media->getCaption(),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'mimeType' => $media->getMimeType(),
            'sizeBytes' => $media->getSizeBytes(),
            'enhancementStatus' => $media->getEnhancementStatus()->value,
            'createdAt' => $media->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<Media> $items
     *
     * @return list<array<string, mixed>>
     */
    public function toArrayList(array $items): array
    {
        return array_map($this->toArray(...), $items);
    }
}
