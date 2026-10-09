<?php

namespace App\Entity\Site\Enum;

enum AdFormat: string
{
    case Leaderboard = 'leaderboard';
    case MediumRectangle = 'medium_rectangle';
    case HalfPage = 'half_page';
    case Billboard = 'billboard';
    case MobileBanner = 'mobile_banner';
    case Responsive = 'responsive';

    public function label(): string
    {
        return match ($this) {
            self::Leaderboard => 'Leaderboard (728×90)',
            self::MediumRectangle => 'Medium Rectangle (300×250)',
            self::HalfPage => 'Half Page (300×600)',
            self::Billboard => 'Billboard (970×250)',
            self::MobileBanner => 'Mobile Banner (320×50)',
            self::Responsive => 'Responsive / personalizado',
        };
    }

    public function cssClass(): string
    {
        return 'ad-format-'.$this->value;
    }
}
