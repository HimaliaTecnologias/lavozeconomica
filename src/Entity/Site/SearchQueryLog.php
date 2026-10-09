<?php

namespace App\Entity\Site;

use App\Repository\Site\SearchQueryLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SearchQueryLogRepository::class)]
#[ORM\Table(name: 'search_query_logs')]
#[ORM\Index(columns: ['created_at'], name: 'idx_search_log_created')]
#[ORM\Index(columns: ['query'], name: 'idx_search_log_query')]
class SearchQueryLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $query;

    #[ORM\Column(name: 'results_count')]
    private int $resultsCount;

    #[ORM\Column(name: 'visitor_hash', length: 16, nullable: true)]
    private ?string $visitorHash;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $query, int $resultsCount, ?string $visitorHash = null)
    {
        $this->query = $query;
        $this->resultsCount = max(0, $resultsCount);
        $this->visitorHash = $visitorHash !== null && $visitorHash !== '' ? $visitorHash : null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getResultsCount(): int
    {
        return $this->resultsCount;
    }

    public function getVisitorHash(): ?string
    {
        return $this->visitorHash;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
