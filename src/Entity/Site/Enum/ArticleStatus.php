<?php

namespace App\Entity\Site\Enum;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
