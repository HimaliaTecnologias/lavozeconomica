<?php

namespace App\Core\Site\Dto;

use App\ContentModules\ContentModuleFormat;
use App\ContentModules\ContentModuleType;

final readonly class ContentModuleItem
{
    public function __construct(
        public ContentModuleType $type,
        public bool $enabled = true,
        public int $sortOrder = 10,
        public ContentModuleFormat $format = ContentModuleFormat::Grid4,
        public int $count = 4,
    ) {}

    /**
     * @return array{type: string, enabled: bool, sortOrder: int, format: string, count: int}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'enabled' => $this->enabled,
            'sortOrder' => $this->sortOrder,
            'format' => $this->format->value,
            'count' => $this->count,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row, ContentModuleType $fallbackType): self
    {
        $type = ContentModuleType::tryFrom((string) ($row['type'] ?? '')) ?? $fallbackType;
        $format = ContentModuleFormat::tryFrom((string) ($row['format'] ?? '')) ?? $type->defaultFormat();
        if (!\in_array($format, $type->allowedFormats(), true)) {
            $format = $type->defaultFormat();
        }

        $count = (int) ($row['count'] ?? $type->defaultCount());
        if ($type->usesArticleList()) {
            $count = max(2, min(12, $count > 0 ? $count : $format->suggestedCount()));
        } else {
            $count = 0;
        }

        return new self(
            type: $type,
            enabled: (bool) ($row['enabled'] ?? true),
            sortOrder: max(0, (int) ($row['sortOrder'] ?? 10)),
            format: $format,
            count: $count,
        );
    }
}
