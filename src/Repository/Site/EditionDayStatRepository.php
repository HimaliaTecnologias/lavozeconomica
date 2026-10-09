<?php

namespace App\Repository\Site;

use App\Entity\Site\EditionDayStat;
use Doctrine\ORM\EntityRepository;

/**
 * @extends EntityRepository<EditionDayStat>
 */
final class EditionDayStatRepository extends EntityRepository
{
    public function findCountForDate(string $editionDate): int
    {
        $stat = $this->findOneBy(['editionDate' => $editionDate]);

        return $stat?->getPdfDownloadCount() ?? 0;
    }

  /**
   * @param list<string> $editionDates
   *
   * @return array<string, int>
   */
    public function findCountsForDates(array $editionDates): array
    {
        if ($editionDates === []) {
            return [];
        }

        /** @var list<EditionDayStat> $rows */
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.editionDate IN (:dates)')
            ->setParameter('dates', $editionDates)
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($editionDates as $date) {
            $counts[$date] = 0;
        }
        foreach ($rows as $row) {
            $counts[$row->getEditionDate()] = $row->getPdfDownloadCount();
        }

        return $counts;
    }

    public function recordPdfDownload(string $editionDate): int
    {
        $em = $this->getEntityManager();
        $stat = $this->findOneBy(['editionDate' => $editionDate]);
        if ($stat === null) {
            $stat = new EditionDayStat($editionDate);
            $em->persist($stat);
        }

        $stat->incrementPdfDownloadCount();
        $em->flush();

        return $stat->getPdfDownloadCount();
    }
}
