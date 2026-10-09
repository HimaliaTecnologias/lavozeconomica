<?php

namespace App\Editorial;

use App\Entity\Site\Article;
use App\Entity\Site\ArticleRelated;
use App\Entity\Site\Enum\ArticleStatus;
use App\Repository\Site\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;

final class ArticleRelatedSyncService
{
    private const MAX_RELATED = 8;

    /**
     * @param list<int|string> $relatedIds
     */
    public function syncForArticle(EntityManagerInterface $em, Article $article, array $relatedIds): void
    {
        $article->clearRelatedLinks();

        $ids = [];
        foreach ($relatedIds as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        $selfId = $article->getId();
        if ($selfId !== null) {
            $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $selfId));
        }
        $ids = array_slice($ids, 0, self::MAX_RELATED);
        if ($ids === []) {
            return;
        }

        $repo = $em->getRepository(Article::class);
        if (!$repo instanceof ArticleRepository) {
            return;
        }

        $relatedArticles = $repo->findByIdsPreservingOrder($ids);
        $sort = 0;
        foreach ($relatedArticles as $related) {
            if ($related->getStatus() === ArticleStatus::Archived) {
                continue;
            }
            $link = new ArticleRelated($article, $related, $sort);
            $article->addRelatedLink($link);
            $em->persist($link);
            ++$sort;
        }
    }
}
