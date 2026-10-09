<?php

namespace App\Messenger;

final readonly class ProcessMediaMessage
{
    public function __construct(
        public string $siteCode,
        public int $mediaId,
        public string $operation,
    ) {}
}
