<?php

namespace App\Editorial\Pdf;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class EditionPdfImageStore
{
    /** @var array<string, string> */
    private array $resolved = [];

    public function __construct(
        private readonly string $publicDir,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {}

    public function prepareWorkDir(string $baseCacheDir, string $token): string
    {
        $dir = rtrim($baseCacheDir, '/').'/edition-'.$token;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

  /**
   * @return string relative path inside work dir (e.g. img-abc.jpg)
   */
    public function store(string $url, string $workDir, string $siteBaseUrl): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (isset($this->resolved[$url])) {
            return $this->resolved[$url];
        }

        $extension = $this->guessExtension($url);
        $filename = 'img-'.substr(hash('sha256', $url), 0, 16).$extension;
        $target = $workDir.'/'.$filename;

        if (is_file($target)) {
            $this->resolved[$url] = $filename;

            return $filename;
        }

        $sourcePath = $this->resolveLocalPath($url, $siteBaseUrl);
        if ($sourcePath !== null && is_file($sourcePath)) {
            if (!copy($sourcePath, $target)) {
                return null;
            }
            $this->resolved[$url] = $filename;

            return $filename;
        }

        if ($this->isRemoteUrl($url) && $this->httpClient !== null) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 20,
                    'max_redirects' => 3,
                ]);
                if ($response->getStatusCode() >= 400) {
                    return null;
                }
                $bytes = $response->getContent();
                if ($bytes === '') {
                    return null;
                }
                file_put_contents($target, $bytes);
                $this->resolved[$url] = $filename;

                return $filename;
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resolveLocalPath(string $url, string $siteBaseUrl): ?string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            if (str_starts_with($url, $siteBaseUrl)) {
                $path = substr($url, strlen($siteBaseUrl));
                if ($path !== '' && $path[0] !== '/') {
                    $path = '/'.$path;
                }

                return $this->publicDir.$path;
            }

            return null;
        }

        if (str_starts_with($url, '//')) {
            return null;
        }

        $path = str_starts_with($url, '/') ? $url : '/'.$url;
        $full = $this->publicDir.$path;

        return is_file($full) ? $full : null;
    }

    private function isRemoteUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//');
    }

    private function guessExtension(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && preg_match('/\.(jpe?g|png|webp|gif)$/i', $path, $m)) {
            $ext = strtolower($m[1]);
            if ($ext === 'jpeg') {
                return '.jpg';
            }

            return '.'.$ext;
        }

        return '.jpg';
    }
}
