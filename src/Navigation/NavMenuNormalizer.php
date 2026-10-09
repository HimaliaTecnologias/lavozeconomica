<?php

namespace App\Navigation;

use App\Core\Site\Dto\NavMenuConfig;

final class NavMenuNormalizer
{
    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeMany(array $rows, array $defaults): array
    {
        if ($rows === []) {
            return $defaults;
        }

        $normalized = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = $this->normalizeOne($row);
            if ($item !== null) {
                $normalized[] = $item;
            }
        }

        if ($normalized === []) {
            return $defaults;
        }

        $normalized = $this->appendMissingDefaults($normalized, $defaults);

        usort($normalized, static fn (array $a, array $b): int => ($a['sortOrder'] ?? 0) <=> ($b['sortOrder'] ?? 0));

        return $normalized;
    }

    /**
     * @param list<array<string, mixed>> $items
     * @param list<array<string, mixed>> $defaults
     *
     * @return list<array<string, mixed>>
     */
    private function appendMissingDefaults(array $items, array $defaults): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        foreach ($defaults as $default) {
            $defaultId = (string) ($default['id'] ?? '');
            if ($defaultId === '' || isset($ids[$defaultId])) {
                continue;
            }
            $items[] = $default;
            $ids[$defaultId] = true;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>|null
     */
    public function normalizeOne(array $row): ?array
    {
        $type = NavMenuItemType::tryFrom((string) ($row['type'] ?? ''));
        if ($type === null) {
            return null;
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = $type->value.'_'.bin2hex(random_bytes(4));
        }

        $label = trim((string) ($row['label'] ?? ''));
        $categoryId = $row['categoryId'] ?? null;
        $categoryId = $categoryId !== null && $categoryId !== '' ? (int) $categoryId : null;
        $pageSlug = trim((string) ($row['pageSlug'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));

        if ($type->needsCategory() && $categoryId === null) {
            return null;
        }
        if ($type->needsPage() && $pageSlug === '') {
            return null;
        }
        if ($type->needsUrl() && $url === '') {
            return null;
        }

        return [
            'id' => $id,
            'type' => $type->value,
            'label' => $label,
            'enabled' => (bool) ($row['enabled'] ?? false),
            'sortOrder' => max(0, (int) ($row['sortOrder'] ?? 0)),
            'categoryId' => $categoryId,
            'pageSlug' => $pageSlug !== '' ? $pageSlug : null,
            'url' => $url,
            'openInNewTab' => (bool) ($row['openInNewTab'] ?? false),
        ];
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    public function toConfig(
        array $mainItems,
        array $footerSectionItems,
        array $footerLegalItems,
    ): NavMenuConfig {
        return new NavMenuConfig(
            mainItems: $this->normalizeMany($mainItems, NavMenuDefaults::mainItems()),
            footerSectionItems: $this->normalizeMany($footerSectionItems, NavMenuDefaults::footerSectionItems()),
            footerLegalItems: $this->normalizeMany($footerLegalItems, NavMenuDefaults::footerLegalItems()),
        );
    }
}
