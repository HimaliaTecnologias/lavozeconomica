<?php

namespace App\Editorial;

final class CommunityRichTextSanitizer
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><a>';

    public function sanitize(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        if (!str_contains($input, '<')) {
            return htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $clean = strip_tags($input, self::ALLOWED_TAGS);
        $clean = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li)\s+[^>]*>/i', '<$1>', $clean) ?? $clean;
        $clean = $this->sanitizeLinks($clean);

        return trim($clean);
    }

    public function plainTextLength(string $html): int
    {
        $text = html_entity_decode(strip_tags($this->sanitize($html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strlen(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }

    public function isEffectivelyEmpty(string $html): bool
    {
        return $this->plainTextLength($html) === 0;
    }

    private function sanitizeLinks(string $html): string
    {
        return preg_replace_callback(
            '/<a\s+([^>]*?)>/i',
            static function (array $matches): string {
                if (preg_match('/href\s*=\s*(["\'])(https?:\/\/[^"\']+)\1/i', $matches[1], $href)
                    || preg_match('/href\s*=\s*(https?:\/\/[^\s>]+)/i', $matches[1], $href)) {
                    $url = htmlspecialchars($href[2] ?? $href[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                    return '<a href="'.$url.'" rel="nofollow noopener" target="_blank">';
                }

                return '<a>';
            },
            $html,
        ) ?? $html;
    }
}
