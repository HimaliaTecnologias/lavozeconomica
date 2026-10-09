<?php

namespace App\Ingestion\HimtecMedia;

final class HimtecMediaPayloadNormalizer
{
    /**
     * @param array<string, mixed> $item
     *
     * @return array<string, mixed>
     */
    public function normalizeStory(array $item): array
    {
        $id = $item['id']
            ?? $item['external_id']
            ?? $item['uuid']
            ?? $item['_id']
            ?? $item['news_id']
            ?? null;

        $title = $item['title']
            ?? $item['titulo']
            ?? $item['titular']
            ?? $item['headline']
            ?? $item['name']
            ?? null;

        $body = $item['body_html']
            ?? $item['bodyHtml']
            ?? $item['body']
            ?? $item['cuerpo']
            ?? $item['contenido']
            ?? $item['content']
            ?? $item['texto']
            ?? $item['html']
            ?? $item['descripcion']
            ?? $item['resumen']
            ?? '';

        if ($id === null || $title === null || trim((string) $title) === '') {
            throw new \InvalidArgumentException('Cada noticia debe incluir id y title (o titulo).');
        }

        return array_merge($item, [
            'id' => (string) $id,
            'title' => trim((string) $title),
            'body_html' => (string) $body,
            'format_version' => (string) ($item['format_version'] ?? $item['formatVersion'] ?? '1.0'),
        ]);
    }
}
