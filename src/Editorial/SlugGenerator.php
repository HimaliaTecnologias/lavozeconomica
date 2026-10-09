<?php

namespace App\Editorial;

use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;

final class SlugGenerator
{
    private const MAX_LENGTH = 80;

    private readonly SluggerInterface $slugger;

    public function __construct()
    {
        $this->slugger = new AsciiSlugger('es');
    }

    public function fromTitle(string $title): string
    {
        $slug = strtolower($this->slugger->slug($title)->toString());

        return $this->finalize($slug);
    }

    public function normalize(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';

        return $this->finalize(trim($slug, '-'));
    }

    private function finalize(string $slug): string
    {
        if ($slug === '') {
            return 'articulo';
        }

        if (strlen($slug) > self::MAX_LENGTH) {
            $truncated = substr($slug, 0, self::MAX_LENGTH);
            $lastHyphen = strrpos($truncated, '-');
            if ($lastHyphen !== false && $lastHyphen > 40) {
                $truncated = substr($truncated, 0, $lastHyphen);
            }

            $slug = rtrim($truncated, '-');
        }

        return $slug !== '' ? $slug : 'articulo';
    }
}
