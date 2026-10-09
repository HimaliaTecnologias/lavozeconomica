<?php

namespace App\Entity\Platform\Enum;

enum SitePublicationStatus: string
{
    case Assigned = 'assigned';
    case Review = 'review';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
