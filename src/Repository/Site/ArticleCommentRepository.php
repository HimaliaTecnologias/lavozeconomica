<?php

namespace App\Repository\Site;

use App\Entity\Site\Article;
use App\Entity\Site\ArticleComment;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ArticleComment>
 */
final class ArticleCommentRepository extends EntityRepository
{
    /** @return list<ArticleComment> */
    public function findForArticle(Article $article, int $limit = 100): array
    {
        return $this->findVisibleForArticle($article, $limit);
    }

    /** @return list<ArticleComment> */
    public function findVisibleForArticle(Article $article, int $limit = 100): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.article = :article')
            ->andWhere('c.isHidden = false')
            ->setParameter('article', $article)
            ->orderBy('c.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** @return list<ArticleComment> */
    public function findRecentForAdmin(int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.article', 'a')
            ->addSelect('a')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUserId(int $userId): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<ArticleComment> */
    public function findByUserId(int $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.article', 'a')
            ->addSelect('a')
            ->andWhere('c.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
