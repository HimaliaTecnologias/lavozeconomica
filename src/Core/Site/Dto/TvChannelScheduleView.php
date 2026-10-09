<?php

namespace App\Core\Site\Dto;

final readonly class TvChannelScheduleView
{
    /**
     * @param list<TvProgramView> $programmes
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $xmltvId,
        public string $group,
        public string $iconUrl,
        public array $programmes,
    ) {}

    public function currentProgramme(?\DateTimeImmutable $now = null): ?TvProgramView
    {
        $now ??= new \DateTimeImmutable();

        foreach ($this->programmes as $programme) {
            if ($programme->isOnNow($now)) {
                return $programme;
            }
        }

        return null;
    }

    /**
     * Programas desde el actual (o el siguiente) hacia adelante.
     *
     * @return list<TvProgramView>
     */
    public function programmesFromNow(int $limit = 12, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $out = [];

        foreach ($this->programmes as $programme) {
            if ($programme->endsAt <= $now) {
                continue;
            }
            $out[] = $programme;
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }
}
