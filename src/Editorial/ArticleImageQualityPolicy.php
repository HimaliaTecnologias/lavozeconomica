<?php

namespace App\Editorial;

final readonly class ArticleImageQualityPolicy
{
    public function __construct(
        private int $minWidthFeatured = 1200,
        private int $minHeightFeatured = 675,
        private int $minWidthGallery = 900,
        private int $minHeightGallery = 500,
    ) {}

    public function minWidth(int $sortOrder): int
    {
        return $sortOrder === 0 ? $this->minWidthFeatured : $this->minWidthGallery;
    }

    public function minHeight(int $sortOrder): int
    {
        return $sortOrder === 0 ? $this->minHeightFeatured : $this->minHeightGallery;
    }

    public function meetsRequirements(int $width, int $height, int $sortOrder): bool
    {
        return $width >= $this->minWidth($sortOrder) && $height >= $this->minHeight($sortOrder);
    }

  /**
   * @return array{width: int, height: int}
   */
    public function targetDimensions(int $width, int $height, int $sortOrder): array
    {
        if ($width <= 0 || $height <= 0) {
            return [
                'width' => $this->minWidth($sortOrder),
                'height' => $this->minHeight($sortOrder),
            ];
        }

        $scale = 1.0;
        $minWidth = $this->minWidth($sortOrder);
        $minHeight = $this->minHeight($sortOrder);

        if ($width < $minWidth) {
            $scale = max($scale, $minWidth / $width);
        }
        if ($height < $minHeight) {
            $scale = max($scale, $minHeight / $height);
        }

        return [
            'width' => (int) round($width * $scale),
            'height' => (int) round($height * $scale),
        ];
    }
}
