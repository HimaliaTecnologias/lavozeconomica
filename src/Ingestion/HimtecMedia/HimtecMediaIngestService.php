<?php

namespace App\Ingestion\HimtecMedia;

use App\Entity\Platform\Enum\IngestedStoryStatus;
use App\Entity\Platform\IngestedStory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Pipeline Fase 0: recepción y persistencia canónica en BD plataforma.
 * Validación JSON Schema, reglas y publicación multi-sitio en fases posteriores.
 */
final class HimtecMediaIngestService
{
    public function __construct(
        #[Autowire(service: 'doctrine.orm.platform_entity_manager')]
        private readonly EntityManagerInterface $platformEm,
    ) {}

    public function ingest(array $payload): IngestedStory
    {
        $externalId = (string) ($payload['id'] ?? $payload['external_id'] ?? '');
        $formatVersion = (string) ($payload['format_version'] ?? '1.0');
        $title = (string) ($payload['title'] ?? '');
        $body = (string) ($payload['body'] ?? $payload['body_html'] ?? '');

        if ($externalId === '' || $title === '') {
            throw new \InvalidArgumentException('Payload inválido: id y title son obligatorios.');
        }

        $existing = $this->platformEm->getRepository(IngestedStory::class)->findOneBy([
            'source' => 'himtecmedia',
            'externalId' => $externalId,
        ]);

        if ($existing !== null) {
            $existing->setStatus(IngestedStoryStatus::Duplicate);

            return $existing;
        }

        $hash = hash('sha256', $title.$body.$externalId);

        $story = new IngestedStory(
            externalId: $externalId,
            formatVersion: $formatVersion,
            title: $title,
            bodyHtml: $body,
            rawPayload: $payload,
            contentHash: $hash,
        );

        $this->platformEm->persist($story);
        $this->platformEm->flush();

        return $story;
    }
}
