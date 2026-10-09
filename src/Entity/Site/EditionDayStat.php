<?php

namespace App\Entity\Site;

use App\Repository\Site\EditionDayStatRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EditionDayStatRepository::class)]
#[ORM\Table(name: 'edition_day_stats')]
#[ORM\UniqueConstraint(name: 'uniq_edition_date', columns: ['edition_date'])]
class EditionDayStat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Fecha de edición en formato Y-m-d (Europe/Madrid). */
    #[ORM\Column(name: 'edition_date', length: 10)]
    private string $editionDate;

    #[ORM\Column(name: 'pdf_download_count', options: ['default' => 0])]
    private int $pdfDownloadCount = 0;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $editionDate)
    {
        $this->editionDate = $editionDate;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEditionDate(): string
    {
        return $this->editionDate;
    }

    public function getPdfDownloadCount(): int
    {
        return $this->pdfDownloadCount;
    }

    public function incrementPdfDownloadCount(): void
    {
        ++$this->pdfDownloadCount;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
