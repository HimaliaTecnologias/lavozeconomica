<?php

namespace App\Editorial;

use App\Entity\Site\Enum\ArticleStatus;

final class ArticleStatusPresenter
{
    /** @return array{label: string, class: string} */
    public static function present(ArticleStatus $status): array
    {
        return match ($status) {
            ArticleStatus::Draft => ['label' => 'Borrador', 'class' => 'status-badge--draft'],
            ArticleStatus::PendingReview => ['label' => 'En revisión', 'class' => 'status-badge--review'],
            ArticleStatus::Scheduled => ['label' => 'Programada', 'class' => 'status-badge--scheduled'],
            ArticleStatus::Published => ['label' => 'Publicada', 'class' => 'status-badge--published'],
            ArticleStatus::Archived => ['label' => 'Papelera', 'class' => 'status-badge--archived'],
        };
    }

    public static function label(ArticleStatus $status): string
    {
        return self::present($status)['label'];
    }

    public static function badgeClass(ArticleStatus $status): string
    {
        return self::present($status)['class'];
    }
}
