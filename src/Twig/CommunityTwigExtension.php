<?php

namespace App\Twig;

use App\Editorial\CommunityRichTextSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class CommunityTwigExtension extends AbstractExtension
{
    public function __construct(
        private readonly CommunityRichTextSanitizer $sanitizer,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('community_rich_text', $this->formatRichText(...), ['is_safe' => ['html']]),
        ];
    }

    public function formatRichText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $sanitized = $this->sanitizer->sanitize($value);
        if (!str_contains($sanitized, '<')) {
            return nl2br($sanitized);
        }

        return $sanitized;
    }
}
