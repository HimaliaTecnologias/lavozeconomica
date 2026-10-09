<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use App\Entity\Site\Media;
use App\Messenger\ProcessMediaMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class MediaProcessingDispatcher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {}

    public function dispatch(SiteContext $site, Media $media, string $operation): bool
    {
        $mediaId = $media->getId();
        if ($mediaId === null) {
            return false;
        }

        $this->messageBus->dispatch(new ProcessMediaMessage($site->code, $mediaId, $operation));

        return true;
    }
}
