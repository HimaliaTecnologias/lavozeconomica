<?php

namespace App\Twig;

use App\Editorial\ArticleStatusPresenter;
use App\Entity\Site\Enum\ArticleStatus;
use App\Markets\MarketFormatter;
use App\Tv\TvFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AdminPresentationExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('article_status_label', $this->articleStatusLabel(...)),
            new TwigFunction('article_status_badge_class', $this->articleStatusBadgeClass(...)),
            new TwigFunction('market_format_price', MarketFormatter::formatPrice(...)),
            new TwigFunction('market_format_change', MarketFormatter::formatChange(...)),
            new TwigFunction('market_format_change_percent', MarketFormatter::formatChangePercent(...)),
            new TwigFunction('market_change_class', MarketFormatter::changeClass(...)),
            new TwigFunction('market_sparkline_path', MarketFormatter::sparklinePath(...)),
            new TwigFunction('market_region_label', MarketFormatter::regionLabel(...)),
            new TwigFunction('tv_format_time', TvFormatter::formatTime(...)),
            new TwigFunction('tv_format_time_range', TvFormatter::formatTimeRange(...)),
            new TwigFunction('tv_format_duration', TvFormatter::formatDuration(...)),
        ];
    }

    public function articleStatusLabel(string|ArticleStatus $status): string
    {
        if ($status instanceof ArticleStatus) {
            return ArticleStatusPresenter::label($status);
        }

        $enum = ArticleStatus::tryFrom($status);

        return $enum !== null ? ArticleStatusPresenter::label($enum) : $status;
    }

    public function articleStatusBadgeClass(string|ArticleStatus $status): string
    {
        if ($status instanceof ArticleStatus) {
            return ArticleStatusPresenter::badgeClass($status);
        }

        $enum = ArticleStatus::tryFrom($status);

        return $enum !== null ? ArticleStatusPresenter::badgeClass($enum) : 'status-badge--draft';
    }
}
