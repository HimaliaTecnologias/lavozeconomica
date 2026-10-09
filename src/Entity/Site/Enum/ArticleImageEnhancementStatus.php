<?php

namespace App\Entity\Site\Enum;

enum ArticleImageEnhancementStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Enhanced = 'enhanced';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
