<?php

namespace App\Tv;

use App\Core\Site\Dto\TvChannelScheduleView;
use App\Core\Site\Dto\TvProgramView;

final class TvXmltvParser
{
    /**
     * @param list<array{id: string, name: string, xmltvId: string, group: string}> $channelDefinitions
     *
     * @return list<TvChannelScheduleView>
     */
    public function parse(
        string $xmlContent,
        array $channelDefinitions,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
    ): array {
        $wantedIds = [];
        $definitionByXmltvId = [];
        foreach ($channelDefinitions as $definition) {
            $wantedIds[$definition['xmltvId']] = true;
            $definitionByXmltvId[$definition['xmltvId']] = $definition;
        }

        $icons = [];
        $programmesByChannel = [];
        foreach ($channelDefinitions as $definition) {
            $programmesByChannel[$definition['xmltvId']] = [];
        }

        $reader = new \XMLReader();
        if (!$reader->XML($xmlContent, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('No se pudo leer la guía XMLTV.');
        }

        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }

            if ($reader->name === 'channel') {
                $channelId = (string) $reader->getAttribute('id');
                if (!isset($wantedIds[$channelId])) {
                    continue;
                }

                $iconUrl = '';
                $depth = $reader->depth;
                while ($reader->read()) {
                    if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $depth) {
                        break;
                    }
                    if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'icon') {
                        $iconUrl = (string) $reader->getAttribute('src');
                    }
                }
                if ($iconUrl !== '') {
                    $icons[$channelId] = $iconUrl;
                }

                continue;
            }

            if ($reader->name !== 'programme') {
                continue;
            }

            $channelId = (string) $reader->getAttribute('channel');
            if (!isset($wantedIds[$channelId])) {
                continue;
            }

            $start = $this->parseXmltvDate((string) $reader->getAttribute('start'));
            $stop = $this->parseXmltvDate((string) $reader->getAttribute('stop'));
            if ($start === null || $stop === null) {
                continue;
            }

            if ($stop <= $dayStart || $start >= $dayEnd) {
                continue;
            }

            $title = '';
            $description = '';
            $category = '';
            $episode = '';
            $depth = $reader->depth;

            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->depth === $depth) {
                    break;
                }
                if ($reader->nodeType !== \XMLReader::ELEMENT) {
                    continue;
                }

                match ($reader->name) {
                    'title' => $title = trim($reader->readString()),
                    'desc' => $description = trim($reader->readString()),
                    'category' => $category = trim($reader->readString()),
                    'episode-num' => $episode = trim($reader->readString()),
                    default => null,
                };
            }

            if ($title === '') {
                continue;
            }

            $programmesByChannel[$channelId][] = new TvProgramView(
                title: $title,
                description: $description,
                category: $category,
                episode: $episode,
                startsAt: $start,
                endsAt: $stop,
            );
        }

        $reader->close();

        $schedules = [];
        foreach ($channelDefinitions as $definition) {
            $xmltvId = $definition['xmltvId'];
            $items = $programmesByChannel[$xmltvId] ?? [];
            usort($items, static fn (TvProgramView $a, TvProgramView $b): int => $a->startsAt <=> $b->startsAt);

            $schedules[] = new TvChannelScheduleView(
                id: $definition['id'],
                name: $definition['name'],
                xmltvId: $xmltvId,
                group: $definition['group'],
                iconUrl: $icons[$xmltvId] ?? '',
                programmes: $items,
            );
        }

        return $schedules;
    }

    private function parseXmltvDate(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $raw, 2);
        $datePart = $parts[0] ?? '';
        $tzPart = $parts[1] ?? '+0000';

        if (strlen($datePart) < 14) {
            return null;
        }

        $normalized = substr($datePart, 0, 14);
        $offset = $tzPart;
        if (preg_match('/^[+-]\d{4}$/', $offset) === 1) {
            $offset = substr($offset, 0, 3).':'.substr($offset, 3);
        }

        $parsed = \DateTimeImmutable::createFromFormat('YmdHis e', $normalized.' '.$offset);

        return $parsed ?: null;
    }
}
