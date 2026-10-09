<?php

namespace App\Core\Site;

use App\Cache\PublicArticleCache;
use App\Entity\Site\Category;
use App\Repository\Site\ArticleRepository;
use App\Repository\Site\CategoryRepository;

final class CategorySectionsProvider
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
        private readonly PublicArticleCache $articleCache,
    ) {}

    /**
     * @param list<Category> $navCategories
     *
     * @return list<array{category: Category, articles: list}>
     */
    public function forNavCategories(SiteContext $site, array $navCategories, int $articlesPerSection): array
    {
        if ($navCategories === []) {
            return [];
        }

        $articlesPerSection = max(2, min(4, $articlesPerSection));
        $em = $this->siteEmFactory->forContext($site);
        $repo = $this->articleRepository($em);

        $categoryRepo = $em->getRepository(Category::class);
        $categoryRepo = $categoryRepo instanceof CategoryRepository ? $categoryRepo : null;

        $sections = [];
        foreach ($navCategories as $category) {
            $categoryIds = $categoryRepo !== null
                ? $categoryRepo->collectSelfAndDescendantIds($category)
                : ($category->getId() !== null ? [$category->getId()] : []);

            if ($categoryIds === []) {
                continue;
            }

            $idsKey = implode('-', $categoryIds);
            $sectionArticles = $this->articleCache->rememberList(
                $site,
                $repo,
                sprintf('section.%s.n%d.v2', $idsKey, $articlesPerSection),
                static fn (): array => $repo->findPublishedByCategoryIds($categoryIds, $articlesPerSection, null, false),
            );
            if ($sectionArticles === []) {
                continue;
            }

            $sections[] = [
                'category' => $category,
                'articles' => $sectionArticles,
            ];
        }

        return $sections;
    }

    private function articleRepository(\Doctrine\ORM\EntityManagerInterface $em): ArticleRepository
    {
        $repo = $em->getRepository(\App\Entity\Site\Article::class);
        if (!$repo instanceof ArticleRepository) {
            throw new \RuntimeException('ArticleRepository no disponible.');
        }

        return $repo;
    }
}
