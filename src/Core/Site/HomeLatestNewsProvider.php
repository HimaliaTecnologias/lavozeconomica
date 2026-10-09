<?php

namespace App\Core\Site;

use App\Entity\Site\Article;

/**
 * Bloques de «Últimas noticias» para portada.
 */
final class HomeLatestNewsProvider
{
    private const FEATURED_COUNT = 4;

    /**
     * @param list<Article>            $homeArticles
     * @param array<int, list<Article>> $relatedById
     *
     * @return list<array{article: Article, related: list<Article>}>
     */
    public function build(array $homeArticles, array $relatedById = [], int $offset = 8): array
    {
        $featured = array_slice($homeArticles, max(0, $offset), self::FEATURED_COUNT);
        if ($featured === []) {
            return [];
        }

        $blocks = [];
        foreach ($featured as $article) {
            $articleId = $article->getId();
            $blocks[] = [
                'article' => $article,
                'related' => $articleId !== null ? ($relatedById[$articleId] ?? []) : [],
            ];
        }

        return $blocks;
    }
}
