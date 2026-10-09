<?php

namespace App\Core\Site;

final class SiteContext
{
    public function __construct(
        public readonly int $id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $domain,
    ) {}
}
