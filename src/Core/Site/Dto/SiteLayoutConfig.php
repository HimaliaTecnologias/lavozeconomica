<?php

namespace App\Core\Site\Dto;

use App\Entity\Site\Enum\ArticleLayoutTemplate;
use App\Entity\Site\Enum\CategoryLayoutTemplate;
use App\Entity\Site\Enum\HomeLayoutTemplate;

final readonly class SiteLayoutConfig
{
    public const SIDEBAR_COUNT_MIN = 3;
    public const SIDEBAR_COUNT_MAX = 12;

    public function __construct(
        public HomeLayoutTemplate $homeTemplate = HomeLayoutTemplate::Classic,
        public CategoryLayoutTemplate $categoryTemplate = CategoryLayoutTemplate::Magazine,
        public ArticleLayoutTemplate $articleTemplate = ArticleLayoutTemplate::Standard,
        public int $relatedArticlesCount = 8,
        public int $homeCategoryArticlesCount = 4,
        public int $sidebarPopularCount = 5,
        public int $sidebarLatestCount = 5,
    ) {}

    /**
     * @return array{
     *     homeTemplate: string,
     *     categoryTemplate: string,
     *     articleTemplate: string,
     *     relatedArticlesCount: int,
     *     homeCategoryArticlesCount: int,
     *     sidebarPopularCount: int,
     *     sidebarLatestCount: int
     * }
     */
    public function toArray(): array
    {
        return [
            'homeTemplate' => $this->homeTemplate->value,
            'categoryTemplate' => $this->categoryTemplate->value,
            'articleTemplate' => $this->articleTemplate->value,
            'relatedArticlesCount' => $this->relatedArticlesCount,
            'homeCategoryArticlesCount' => $this->homeCategoryArticlesCount,
            'sidebarPopularCount' => $this->sidebarPopularCount,
            'sidebarLatestCount' => $this->sidebarLatestCount,
        ];
    }

    public static function clampSidebarCount(int $value, int $default = 5): int
    {
        if ($value < self::SIDEBAR_COUNT_MIN || $value > self::SIDEBAR_COUNT_MAX) {
            return $default;
        }

        return $value;
    }
}
