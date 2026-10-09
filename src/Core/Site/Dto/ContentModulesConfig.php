<?php

namespace App\Core\Site\Dto;

use App\ContentModules\ContentModuleFormat;
use App\ContentModules\ContentModuleSurface;
use App\ContentModules\ContentModuleType;

final readonly class ContentModulesConfig
{
    /**
     * @param array<string, list<ContentModuleItem>> $surfaces
     */
    public function __construct(
        public array $surfaces = [],
    ) {}

    /**
     * @return list<ContentModuleItem>
     */
    public function enabledFor(ContentModuleSurface|string $surface): array
    {
        $key = $surface instanceof ContentModuleSurface ? $surface->value : $surface;
        $items = $this->surfaces[$key] ?? self::defaultsForSurface(
            ContentModuleSurface::tryFrom($key) ?? ContentModuleSurface::StaticPage,
        );

        $enabled = array_values(array_filter(
            $items,
            static fn (ContentModuleItem $item): bool => $item->enabled,
        ));

        usort($enabled, static fn (ContentModuleItem $a, ContentModuleItem $b): int => $a->sortOrder <=> $b->sortOrder);

        return $enabled;
    }

    /**
     * @return list<ContentModuleItem>
     */
    public function allFor(ContentModuleSurface $surface): array
    {
        $items = $this->surfaces[$surface->value] ?? self::defaultsForSurface($surface);
        usort($items, static fn (ContentModuleItem $a, ContentModuleItem $b): int => $a->sortOrder <=> $b->sortOrder);

        return $items;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function toArray(): array
    {
        $out = [];
        foreach ($this->surfaces as $surface => $items) {
            $out[$surface] = array_map(
                static fn (ContentModuleItem $item): array => $item->toArray(),
                $items,
            );
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromStored(array $raw): self
    {
        $surfaces = [];
        foreach (ContentModuleSurface::cases() as $surface) {
            $rows = $raw[$surface->value] ?? null;
            if (!\is_array($rows) || $rows === []) {
                $surfaces[$surface->value] = self::defaultsForSurface($surface);
                continue;
            }
            $surfaces[$surface->value] = self::normalizeSurfaceRows($surface, $rows);
        }

        return new self($surfaces);
    }

    /**
     * @return list<ContentModuleItem>
     */
    public static function defaultsForSurface(ContentModuleSurface $surface): array
    {
        return match ($surface) {
            ContentModuleSurface::Article => [
                new ContentModuleItem(ContentModuleType::Latest, false, 10, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::Popular, false, 20, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::Related, true, 30, ContentModuleFormat::Grid4, 8),
                new ContentModuleItem(ContentModuleType::CategorySections, false, 40, ContentModuleFormat::LeadStack, 0),
                new ContentModuleItem(ContentModuleType::Accommodations, true, 50, ContentModuleFormat::Default, 0),
            ],
            ContentModuleSurface::Category => [
                new ContentModuleItem(ContentModuleType::Latest, false, 10, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::Popular, false, 20, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::Related, false, 30, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::CategorySections, false, 40, ContentModuleFormat::LeadStack, 0),
                new ContentModuleItem(ContentModuleType::Accommodations, true, 50, ContentModuleFormat::Default, 0),
            ],
            ContentModuleSurface::StaticPage, ContentModuleSurface::Contact => [
                new ContentModuleItem(ContentModuleType::Latest, true, 10, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::Popular, true, 20, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::Related, false, 30, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::CategorySections, true, 40, ContentModuleFormat::LeadStack, 0),
                new ContentModuleItem(ContentModuleType::Accommodations, false, 50, ContentModuleFormat::Default, 0),
            ],
            ContentModuleSurface::LocalServices => [
                new ContentModuleItem(ContentModuleType::Latest, false, 10, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::Popular, false, 20, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::Related, false, 30, ContentModuleFormat::Grid4, 4),
                new ContentModuleItem(ContentModuleType::CategorySections, false, 40, ContentModuleFormat::LeadStack, 0),
                new ContentModuleItem(ContentModuleType::Accommodations, false, 50, ContentModuleFormat::Default, 0),
            ],
        };
    }

    /**
     * @param list<mixed> $rows
     *
     * @return list<ContentModuleItem>
     */
    private static function normalizeSurfaceRows(ContentModuleSurface $surface, array $rows): array
    {
        $byType = [];
        foreach ($rows as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $type = ContentModuleType::tryFrom((string) ($row['type'] ?? ''));
            if ($type === null || !\in_array($type, $surface->availableTypes(), true)) {
                continue;
            }
            $byType[$type->value] = ContentModuleItem::fromArray($row, $type);
        }

        $order = 10;
        $items = [];
        foreach ($surface->availableTypes() as $type) {
            if (isset($byType[$type->value])) {
                $items[] = $byType[$type->value];
            } else {
                $default = null;
                foreach (self::defaultsForSurface($surface) as $candidate) {
                    if ($candidate->type === $type) {
                        $default = $candidate;
                        break;
                    }
                }
                $items[] = $default ?? new ContentModuleItem(
                    $type,
                    false,
                    $order,
                    $type->defaultFormat(),
                    $type->defaultCount(),
                );
            }
            $order += 10;
        }

        return $items;
    }
}
