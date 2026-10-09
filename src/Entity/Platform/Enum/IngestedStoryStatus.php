<?php

namespace App\Entity\Platform\Enum;

enum IngestedStoryStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Failed = 'failed';
    case Duplicate = 'duplicate';
}
