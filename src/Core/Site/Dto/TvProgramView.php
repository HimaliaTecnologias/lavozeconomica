<?php

namespace App\Core\Site\Dto;

final readonly class TvProgramView
{
    public function __construct(
        public string $title,
        public string $description,
        public string $category,
        public string $episode,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
    ) {}

    public function isOnNow(?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $this->startsAt <= $now && $this->endsAt > $now;
    }
}
