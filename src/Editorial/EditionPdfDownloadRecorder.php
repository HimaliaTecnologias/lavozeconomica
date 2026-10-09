<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteEntityManagerFactory;
use App\Entity\Site\EditionDayStat;
use App\Repository\Site\EditionDayStatRepository;

final class EditionPdfDownloadRecorder
{
    public function __construct(
        private readonly SiteEntityManagerFactory $siteEmFactory,
    ) {}

    public function record(SiteContext $site, \DateTimeImmutable $editionDay): int
    {
        $repo = $this->repository($this->siteEmFactory->forContext($site));

        return $repo->recordPdfDownload($editionDay->format('Y-m-d'));
    }

    public function countForDate(SiteContext $site, \DateTimeImmutable $editionDay): int
    {
        $repo = $this->repository($this->siteEmFactory->forContext($site));

        return $repo->findCountForDate($editionDay->format('Y-m-d'));
    }

    /**
     * @param list<string> $editionDates
     *
     * @return array<string, int>
     */
    public function countsForDates(SiteContext $site, array $editionDates): array
    {
        $repo = $this->repository($this->siteEmFactory->forContext($site));

        return $repo->findCountsForDates($editionDates);
    }

    private function repository(\Doctrine\ORM\EntityManagerInterface $em): EditionDayStatRepository
    {
        $repo = $em->getRepository(EditionDayStat::class);
        if (!$repo instanceof EditionDayStatRepository) {
            throw new \RuntimeException('EditionDayStatRepository no disponible.');
        }

        return $repo;
    }
}
