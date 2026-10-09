<?php

namespace App\Repository\Site;

use App\Entity\Site\Media;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<Media>
 */
final class MediaRepository extends EntityRepository
{
    public function findOneByPublicPath(string $publicPath): ?Media
    {
        return $this->findOneBy(['publicPath' => $publicPath]);
    }

    /**
     * @return array{items: list<Media>, total: int}
     */
    public function searchPaginated(string $query, int $page, int $perPage, ?string $kind = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC');

        if ($query !== '') {
            $qb->andWhere('m.originalFilename LIKE :q OR m.title LIKE :q OR m.alt LIKE :q')
                ->setParameter('q', '%'.$query.'%');
        }

        if ($kind === 'image') {
            $qb->andWhere('m.mimeType LIKE :imagePrefix')
                ->setParameter('imagePrefix', 'image/%');
        } elseif ($kind === 'video') {
            $qb->andWhere('m.mimeType LIKE :videoPrefix')
                ->setParameter('videoPrefix', 'video/%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'total' => $total,
        ];
    }
}
