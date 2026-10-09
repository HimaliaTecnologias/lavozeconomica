<?php

namespace App\Messenger;

final readonly class EnhanceArticleImageMessage
{
    public function __construct(
        public string $siteCode,
        public int $articleImageId,
    ) {}
}
