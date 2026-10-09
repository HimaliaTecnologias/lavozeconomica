<?php

namespace App\Editorial;

use App\Entity\Site\Article;
use App\Entity\Site\Category;
use App\Entity\Site\Enum\NewsletterFrequency;
use App\Entity\Site\NewsletterSubscriber;
use App\Repository\Site\ArticleRepository;
use App\Repository\Site\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

final class NewsletterDigestBuilder
{
    private const MAX_ARTICLES = 12;

    public function buildForSubscriber(
        NewsletterSubscriber $subscriber,
        EntityManagerInterface $em,
    ): array {
        $since = $subscriber->getLastDigestSentAt();
        if ($since === null) {
            $since = new \DateTimeImmutable('-'.$subscriber->getFrequency()->periodSeconds().' seconds');
        }

        $categoryIds = $this->resolveCategoryFilterIds($subscriber, $em);
        $articles = $this->articleRepository($em)->findPublishedSince($since, $categoryIds, self::MAX_ARTICLES);

        return [
            'articles' => $articles,
            'since' => $since,
            'periodLabel' => $this->periodLabel($subscriber->getFrequency()),
        ];
    }

    /** @return list<int> */
    private function resolveCategoryFilterIds(NewsletterSubscriber $subscriber, EntityManagerInterface $em): array
    {
        $selected = $subscriber->getCategories();
        if ($selected->isEmpty()) {
            return [];
        }

        $categoryRepo = $em->getRepository(Category::class);
        if (!$categoryRepo instanceof CategoryRepository) {
            return [];
        }

        $ids = [];
        foreach ($selected as $category) {
            $ids = array_merge($ids, $categoryRepo->collectSelfAndDescendantIds($category));
        }

        return array_values(array_unique($ids));
    }

    private function periodLabel(NewsletterFrequency $frequency): string
    {
        return match ($frequency) {
            NewsletterFrequency::Daily => 'Resumen diario',
            NewsletterFrequency::Weekly => 'Resumen semanal',
        };
    }

    private function articleRepository(EntityManagerInterface $em): ArticleRepository
    {
        $repo = $em->getRepository(Article::class);
        if (!$repo instanceof ArticleRepository) {
            throw new \RuntimeException('ArticleRepository no disponible.');
        }

        return $repo;
    }
}
