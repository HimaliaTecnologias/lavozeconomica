<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\Enum\ArticleImageEnhancementStatus;
use App\Entity\Site\Media;
use App\Messenger\EnhanceMediaMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class MediaEnhancementDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function dispatchIfNeeded(SiteContext $site, Media $media): bool
    {
        if ($media->isVideo()) {
            return false;
        }

        if ($media->getEnhancementStatus() !== ArticleImageEnhancementStatus::Pending) {
            return false;
        }

        $mediaId = $media->getId();
        if ($mediaId === null) {
            return false;
        }

        $this->messageBus->dispatch(new EnhanceMediaMessage($site->code, $mediaId));

        return true;
    }
}
