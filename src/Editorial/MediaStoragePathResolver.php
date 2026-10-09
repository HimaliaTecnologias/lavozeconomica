<?php

namespace App\Editorial;

use App\Core\Site\SiteContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MediaStoragePathResolver
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    /**
     * @return array{relativeDir: string, absoluteDir: string, publicDir: string}
     */
    public function libraryDirectory(SiteContext $site, \DateTimeImmutable $when = new \DateTimeImmutable()): array
    {
        $relativeDir = sprintf(
            'media/sites/%s/library/%s/%s',
            $site->code,
            $when->format('Y'),
            $when->format('m'),
        );

        return [
            'relativeDir' => $relativeDir,
            'absoluteDir' => $this->projectDir.'/public/'.$relativeDir,
            'publicDir' => '/'.$relativeDir,
        ];
    }

    /**
     * @return array{relativeDir: string, absoluteDir: string, publicDir: string}
     */
    public function brandingDirectory(SiteContext $site): array
    {
        $relativeDir = sprintf('media/sites/%s/branding', $site->code);

        return [
            'relativeDir' => $relativeDir,
            'absoluteDir' => $this->projectDir.'/public/'.$relativeDir,
            'publicDir' => '/'.$relativeDir,
        ];
    }

    public function absolutePathFromPublic(string $publicPath): string
    {
        return $this->projectDir.'/public'.str_replace('/', DIRECTORY_SEPARATOR, $publicPath);
    }

    public function generateStoredFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)).'.'.$extension;
    }
}
