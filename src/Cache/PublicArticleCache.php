<?php

namespace App\Cache;

use App\Core\Site\SiteContext;
use App\Entity\Site\Article;
use App\Repository\Site\ArticleRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Caches public article listing IDs (tag-aware pool: Redis or filesystem) and hydrates on hit.
 * Avoids repeating expensive published-list queries on home/category/article sidebars.
 */
final class PublicArticleCache
{
    public const DEFAULT_TTL = 90;

    public function __construct(
        #[Autowire(service: 'cache.public_articles')]
        private readonly TagAwareCacheInterface $cache,
    ) {}

    public function siteTag(SiteContext $site): string
    {
        return 'site-articles-'.$site->code;
    }

    public function invalidateSite(SiteContext $site): void
    {
        try {
            $this->cache->invalidateTags([$this->siteTag($site)]);
        } catch (\Throwable) {
            // Redis caído: la invalidación se omite; el TTL limpia solo.
        }
    }

    /**
     * @param callable(): list<Article> $loader
     *
     * @return list<Article>
     */
    public function rememberList(
        SiteContext $site,
        ArticleRepository $repo,
        string $key,
        callable $loader,
        int $ttl = self::DEFAULT_TTL,
    ): array {
        $cacheKey = $this->key($site, $key);
        $loaded = null;

        try {
            /** @var list<int> $ids */
            $ids = $this->cache->get($cacheKey, function (ItemInterface $item) use ($loader, $site, $ttl, &$loaded): array {
                $item->expiresAfter($ttl);
                $item->tag([$this->siteTag($site)]);
                $loaded = $loader();

                return $this->idsFromArticles($loaded);
            });
        } catch (\Throwable) {
            return $loader();
        }

        if (\is_array($loaded)) {
            return $loaded;
        }

        return $repo->findPublishedByIdsPreservingOrder($ids);
    }

    /**
     * @param callable(): array{items: list<Article>, total: int} $loader
     *
     * @return array{items: list<Article>, total: int}
     */
    public function rememberPage(
        SiteContext $site,
        ArticleRepository $repo,
        string $key,
        callable $loader,
        int $ttl = self::DEFAULT_TTL,
    ): array {
        $cacheKey = $this->key($site, $key);
        $loaded = null;

        try {
            /** @var array{ids: list<int>, total: int} $payload */
            $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($loader, $site, $ttl, &$loaded): array {
                $item->expiresAfter($ttl);
                $item->tag([$this->siteTag($site)]);
                $loaded = $loader();

                return [
                    'ids' => $this->idsFromArticles($loaded['items']),
                    'total' => $loaded['total'],
                ];
            });
        } catch (\Throwable) {
            return $loader();
        }

        if (\is_array($loaded)) {
            return $loaded;
        }

        return [
            'items' => $repo->findPublishedByIdsPreservingOrder($payload['ids']),
            'total' => $payload['total'],
        ];
    }

    public function rememberPublishedArticle(
        SiteContext $site,
        ArticleRepository $repo,
        string $slug,
        int $ttl = self::DEFAULT_TTL,
    ): ?Article {
        $fromCallback = false;
        $article = null;

        try {
            /** @var int $id */
            $id = $this->cache->get($this->key($site, 'slug.'.$slug), function (ItemInterface $item) use ($repo, $slug, $site, $ttl, &$fromCallback, &$article): int {
                $item->expiresAfter($ttl);
                $item->tag([$this->siteTag($site)]);
                $fromCallback = true;
                $article = $repo->findPublishedBySlug($slug);

                return $article?->getId() ?? 0;
            });
        } catch (\Throwable) {
            return $repo->findPublishedBySlug($slug);
        }

        if ($fromCallback) {
            return $article;
        }

        return $id > 0 ? $repo->findPublishedById($id) : null;
    }

    private function key(SiteContext $site, string $key): string
    {
        return 'pub.articles.'.$site->code.'.'.str_replace(['{', '}', '(', ')', '/', '\\', '@', ':'], '_', $key);
    }

    /**
     * @param list<Article> $articles
     *
     * @return list<int>
     */
    private function idsFromArticles(array $articles): array
    {
        $ids = [];
        foreach ($articles as $article) {
            $id = $article->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
