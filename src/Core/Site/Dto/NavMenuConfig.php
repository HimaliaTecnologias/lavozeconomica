<?php

namespace App\Core\Site\Dto;

final readonly class NavMenuConfig
{
    /**
     * @param list<array<string, mixed>> $mainItems
     * @param list<array<string, mixed>> $footerSectionItems
     * @param list<array<string, mixed>> $footerLegalItems
     */
    public function __construct(
        public array $mainItems = [],
        public array $footerSectionItems = [],
        public array $footerLegalItems = [],
    ) {}
}
