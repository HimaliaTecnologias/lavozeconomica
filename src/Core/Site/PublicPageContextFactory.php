<?php

namespace App\Core\Site;

use App\Entity\Site\Category;
use App\Repository\Site\CategoryRepository;

final class PublicPageContextFactory
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {}

    /** @return array{theme: array, appearance: array, navCategories: list<Category>, layout: array{homeTemplate: string, categoryTemplate: string, articleTemplate: string, relatedArticlesCount: int, homeCategoryArticlesCount: int, sidebarPopularCount: int, sidebarLatestCount: int}, footer: mixed} */
    public function build(SiteContext $site): array
    {
        $em = $this->siteEmFactory->forContext($site);
        $categoryRepo = $em->getRepository(Category::class);
        if (!$categoryRepo instanceof CategoryRepository) {
            throw new \RuntimeException('CategoryRepository no disponible.');
        }
        $navCategories = $categoryRepo->findNavRoots();
        $layoutConfig = $this->settingsReader->getLayoutConfig($site);
        $contentModules = $this->settingsReader->getContentModulesConfig($site);

        return [
            'theme' => $this->settingsReader->getTheme($site),
            'appearance' => $this->settingsReader->getAppearance($site)->toArray(),
            'navCategories' => $navCategories,
            'layout' => $layoutConfig->toArray(),
            'footer' => $this->settingsReader->getFooterConfig($site),
            'contentModulesConfig' => $contentModules,
        ];
    }
}
