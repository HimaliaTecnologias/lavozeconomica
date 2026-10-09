<?php

namespace App\Ingestion\HimtecMedia;

use App\Core\Site\SiteContext;
use App\Entity\Platform\IngestFetchRun;
use App\Entity\Platform\Site;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class IngestFetchHistoryRecorder
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {}

    public function record(
        SiteContext $context,
        HimtecMediaFetchResult $result,
        string $trigger,
        ?string $requestUrl = null,
    ): IngestFetchRun {
        $site = $this->platformEm->find(Site::class, $context->id);
        if ($site === null) {
            throw new \RuntimeException('Sitio de plataforma no encontrado.');
        }

        $status = $result->isSuccessful()
            ? $result->summary()
            : ($result->errorMessages[0] ?? $result->summary());

        $run = new IngestFetchRun($site, $trigger, $status, $result->isSuccessful());
        $run->setRequestUrl($requestUrl)
            ->setFeedTotal($result->feedTotal)
            ->setReceived($result->received)
            ->setDuplicates($result->duplicates)
            ->setPublished($result->published)
            ->setErrors($result->errors)
            ->setErrorMessages($result->errorMessages)
            ->setItems(array_map(static fn (HimtecMediaFetchItemResult $item) => $item->toArray(), $result->items));

        $this->platformEm->persist($run);
        $this->platformEm->flush();

        return $run;
    }
}
