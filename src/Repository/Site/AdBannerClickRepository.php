<?php

namespace App\Repository\Site;

use App\Entity\Site\AdBanner;
use App\Entity\Site\AdBannerClick;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<AdBannerClick>
 */
final class AdBannerClickRepository extends EntityRepository
{
  /**
     * @return array<string, int> Y-m-d => count
     */
    public function countGroupedByDay(\DateTimeImmutable $from, \DateTimeImmutable $to, ?int $bannerId = null): array
    {
        $sql = 'SELECT DATE(clicked_at) AS day, COUNT(id) AS total FROM ad_banner_clicks
            WHERE clicked_at >= :from AND clicked_at < :to';
        $params = [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ];

        if ($bannerId !== null) {
            $sql .= ' AND banner_id = :bannerId';
            $params['bannerId'] = $bannerId;
        }

        $sql .= ' GROUP BY day ORDER BY day ASC';

        $rows = $this->getEntityManager()->getConnection()->executeQuery($sql, $params)->fetchAllAssociative();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['day']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * @return array<int, int> bannerId => count
     */
    public function countGroupedByBanner(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.banner) AS bannerId', 'COUNT(c.id) AS total')
            ->where('c.clickedAt >= :from')
            ->andWhere('c.clickedAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('c.banner')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['bannerId']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countTotal(\DateTimeImmutable $from, \DateTimeImmutable $to, ?int $bannerId = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.clickedAt >= :from')
            ->andWhere('c.clickedAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to);

        if ($bannerId !== null) {
            $qb->andWhere('c.banner = :bannerId')
                ->setParameter('bannerId', $bannerId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countForBannerInRange(AdBanner $banner, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $bannerId = $banner->getId();
        if ($bannerId === null) {
            return 0;
        }

        return $this->countTotal($from, $to, $bannerId);
    }
}
