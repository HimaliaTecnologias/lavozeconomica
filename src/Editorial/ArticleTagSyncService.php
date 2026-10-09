<?php

namespace App\Editorial;

use App\Entity\Site\Article;
use App\Entity\Site\ArticleTag;
use Doctrine\ORM\EntityManagerInterface;

final class ArticleTagSyncService
{
    public function __construct(
        private readonly SlugGenerator $slugGenerator,
        private readonly ArticleTagGenerator $tagGenerator,
    ) {}

    /**
     * @return list<string>
     */
    public function parseTagNames(string $input): array
    {
        $parts = preg_split('/[,;]+/u', $input) ?? [];
        $names = [];

        foreach ($parts as $part) {
            $name = trim($part);
            if ($name === '') {
                continue;
            }

            if (strlen($name) > 120) {
                $name = substr($name, 0, 120);
            }

            $names[] = $name;
        }

        return array_values(array_unique($names));
    }

    public function syncForArticle(EntityManagerInterface $em, Article $article, string $extraInput = ''): void
    {
        $autoNames = $this->tagGenerator->suggestForArticle($article);
        $extraNames = $this->parseTagNames($extraInput);
        $names = $this->mergeNames($autoNames, $extraNames);

        $this->syncFromNames($em, $article, $names);
    }

    /**
     * @return list<string>
     */
    public function suggestForArticle(Article $article): array
    {
        return $this->tagGenerator->suggestForArticle($article);
    }

    /**
     * Etiquetas actuales que no saldrían de la autogeneración (extras manuales).
     */
    public function extraTagsInputForArticle(Article $article): string
    {
        $autoSlugs = [];
        foreach ($this->tagGenerator->suggestForArticle($article) as $name) {
            $autoSlugs[] = $this->slugGenerator->fromTitle($name);
        }

        $extra = [];
        foreach ($article->getTags() as $tag) {
            if (!in_array($tag->getSlug(), $autoSlugs, true)) {
                $extra[] = $tag->getName();
            }
        }

        return implode(', ', $extra);
    }

    /**
     * @param list<string> $names
     */
    public function syncFromNames(EntityManagerInterface $em, Article $article, array $names): void
    {
        $tagRepo = $em->getRepository(ArticleTag::class);
        $resolved = [];
        /** @var array<string, ArticleTag> $resolvedBySlug */
        $resolvedBySlug = [];

        foreach ($names as $name) {
            $slug = $this->slugGenerator->fromTitle($name);
            if (isset($resolvedBySlug[$slug])) {
                $resolved[] = $resolvedBySlug[$slug];

                continue;
            }

            $tag = $tagRepo->findOneBy(['slug' => $slug]);
            if ($tag === null) {
                $tag = new ArticleTag($name, $slug);
                $em->persist($tag);
            }

            $resolvedBySlug[$slug] = $tag;
            $resolved[] = $tag;
        }

        $article->getTags()->clear();
        foreach ($resolved as $tag) {
            $article->addTag($tag);
        }
    }

    /**
     * @param list<string> $autoNames
     * @param list<string> $extraNames
     *
     * @return list<string>
     */
    private function mergeNames(array $autoNames, array $extraNames): array
    {
        $seenSlugs = [];
        $merged = [];

        foreach (array_merge($autoNames, $extraNames) as $name) {
            $slug = $this->slugGenerator->fromTitle($name);
            if (isset($seenSlugs[$slug])) {
                continue;
            }
            $seenSlugs[$slug] = true;
            $merged[] = $name;
        }

        return $merged;
    }
}
