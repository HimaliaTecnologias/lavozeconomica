<?php

namespace App\Repository\Site;

use App\Entity\Site\Article;
use App\Entity\Site\ArticleRating;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ArticleRating>
 */
final class ArticleRatingRepository extends EntityRepository
{
    public function findForUser(Article $article, int $userId): ?ArticleRating
    {
        return $this->findOneBy([
            'article' => $article,
            'userId' => $userId,
        ]);
    }

    /** @return array{average: float, count: int} */
    public function summarizeForArticle(Article $article): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.score) as avgScore', 'COUNT(r.id) as total')
            ->andWhere('r.article = :article')
            ->setParameter('article', $article)
            ->getQuery()
            ->getSingleResult();

        return [
            'average' => round((float) ($result['avgScore'] ?? 0), 1),
            'count' => (int) ($result['total'] ?? 0),
        ];
    }
}
