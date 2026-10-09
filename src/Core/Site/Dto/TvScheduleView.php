<?php

namespace App\Core\Site\Dto;

final readonly class TvScheduleView
{
    /**
     * @param list<TvChannelScheduleView> $channels
     */
    public function __construct(
        public array $channels,
        public \DateTimeImmutable $fetchedAt,
        public \DateTimeImmutable $scheduleDate,
        public string $timezone,
    ) {}

    /**
     * @return list<array{channel: TvChannelScheduleView, programme: TvProgramView}>
     */
    public function nowOnTv(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $items = [];

        foreach ($this->channels as $channel) {
            $programme = $channel->currentProgramme($now);
            if ($programme !== null) {
                $items[] = ['channel' => $channel, 'programme' => $programme];
            }
        }

        return $items;
    }
}
