<?php

namespace App\Entity\Site;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gallery_images')]
#[ORM\Index(columns: ['gallery_id', 'sort_order'], name: 'idx_gallery_image_order')]
class GalleryImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Gallery::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'gallery_id', nullable: false, onDelete: 'CASCADE')]
    private Gallery $gallery;

    #[ORM\Column(length: 2048)]
    private string $url;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(name: 'sort_order', options: ['default' => 0])]
    private int $sortOrder = 0;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(name: 'media_id', nullable: true, onDelete: 'SET NULL')]
    private ?Media $media = null;

    public function __construct(Gallery $gallery, string $url, int $sortOrder = 0)
    {
        $this->gallery = $gallery;
        $this->url = $url;
        $this->sortOrder = $sortOrder;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGallery(): Gallery
    {
        return $this->gallery;
    }

    public function setGallery(?Gallery $gallery): self
    {
        if ($gallery !== null) {
            $this->gallery = $gallery;
        }

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): self
    {
        $this->alt = $alt;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getMedia(): ?Media
    {
        return $this->media;
    }

    public function setMedia(?Media $media): self
    {
        $this->media = $media;

        return $this;
    }

    public function applyFromLibraryMedia(Media $media): self
    {
        $this->media = $media;
        $this->url = $media->getPublicPath();
        if ($media->getAlt() !== null) {
            $this->alt = $media->getAlt();
        }
        if ($media->getCaption() !== null) {
            $this->caption = $media->getCaption();
        }

        return $this;
    }
}
