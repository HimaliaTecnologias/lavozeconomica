<?php

namespace App\Repository\Site;

use App\Entity\Site\Article;
use App\Entity\Site\Gallery;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Gallery>
 */
final class GalleryRepository extends EntityRepository
{
    /** @return list<Gallery> */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->leftJoin('g.images', 'i')->addSelect('i')
            ->orderBy('g.updatedAt', 'DESC')
            ->addOrderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<int, int> galleryId => articleCount */
    public function countArticlesByGallery(): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(a.gallery) AS galleryId', 'COUNT(a.id) AS cnt')
            ->from(Article::class, 'a')
            ->andWhere('a.gallery IS NOT NULL')
            ->groupBy('a.gallery')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $id = (int) ($row['galleryId'] ?? 0);
            if ($id > 0) {
                $counts[$id] = (int) ($row['cnt'] ?? 0);
            }
        }

        return $counts;
    }
}
