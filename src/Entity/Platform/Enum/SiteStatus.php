<?php

namespace App\Entity\Platform\Enum;

enum SiteStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
