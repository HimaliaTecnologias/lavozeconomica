<?php

namespace App\Messenger;

final readonly class EnhanceMediaMessage
{
    public function __construct(
        public string $siteCode,
        public int $mediaId,
    ) {}
}
