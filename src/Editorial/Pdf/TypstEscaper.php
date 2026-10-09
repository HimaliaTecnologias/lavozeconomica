<?php

namespace App\Editorial\Pdf;

final class TypstEscaper
{
    public static function content(string $text): string
    {
        return str_replace(
            ['\\', '#', '$', '@', '[', ']', '{', '}', '_'],
            ['\\\\', '\\#', '\\$', '\\@', '\\[', '\\]', '\\{', '\\}', '\\_'],
            $text,
        );
    }

    public static function path(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
