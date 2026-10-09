<?php

namespace App\Core\Site;

final class SiteContextHolder
{
    private ?SiteContext $context = null;

    public function set(SiteContext $context): void
    {
        $this->context = $context;
    }

    public function get(): ?SiteContext
    {
        return $this->context;
    }

    public function require(): SiteContext
    {
        if ($this->context === null) {
            throw new \RuntimeException('Site context not resolved.');
        }

        return $this->context;
    }
}
