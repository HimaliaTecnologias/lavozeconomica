<?php

namespace App\Repository\Site;

use App\Entity\Site\ArticleTag;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<ArticleTag>
 */
final class ArticleTagRepository extends EntityRepository
{
    public function findOneBySlug(string $slug): ?ArticleTag
    {
        return $this->findOneBy(['slug' => $slug]);
    }
}
