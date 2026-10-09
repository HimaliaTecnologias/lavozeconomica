<?php

namespace App\Messenger;

final readonly class SendNewsletterDigestMessage
{
    public function __construct(
        public string $siteCode,
        public int $subscriberId,
    ) {}
}
