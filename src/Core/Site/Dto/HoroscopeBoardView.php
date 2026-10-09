<?php

namespace App\Core\Site\Dto;

final readonly class HoroscopeBoardView
{
    /**
     * @param list<HoroscopeSignView> $signs
     */
    public function __construct(
        public array $signs,
        public \DateTimeImmutable $date,
        public \DateTimeImmutable $generatedAt,
    ) {}

    public function find(string $slug): ?HoroscopeSignView
    {
        foreach ($this->signs as $sign) {
            if ($sign->slug === $slug) {
                return $sign;
            }
        }

        return null;
    }
}
