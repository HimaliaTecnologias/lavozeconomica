<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\Article;
use App\Entity\Site\ArticleImage;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Messenger\EnhanceArticleImageMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class ArticleImageEnhancementDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function dispatchForArticle(SiteContext $site, Article $article): int
    {
        $count = 0;

        foreach ($article->getImages() as $image) {
            if ($this->dispatchForImage($site, $image)) {
                ++$count;
            }
        }

        return $count;
    }

    public function dispatchForImage(SiteContext $site, ArticleImage $image): bool
    {
        if ($image->getMedia() !== null) {
            return false;
        }

        if ($image->getEnhancementStatus() !== ArticleImageEnhancementStatus::Pending) {
            return false;
        }

        $imageId = $image->getId();
        if ($imageId === null) {
            return false;
        }

        $this->messageBus->dispatch(new EnhanceArticleImageMessage($site->code, $imageId));

        return true;
    }
}
