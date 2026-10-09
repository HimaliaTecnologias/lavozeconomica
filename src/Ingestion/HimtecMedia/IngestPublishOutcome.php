<?php

namespace App\Ingestion\HimtecMedia;

use App\Entity\Site\Article;

final readonly class IngestPublishOutcome
{
    public function __construct(
        public ?Article $article,
        public bool $created,
    ) {}
}
