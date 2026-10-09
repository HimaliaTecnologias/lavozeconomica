<?php

namespace App\Editorial;

use App\Entity\Site\Article;

final class ArticleTagGenerator
{
    private const MAX_TAGS = 8;
    private const MIN_WORD_LENGTH = 4;
    private const MAX_TITLE_WORDS = 4;
    private const MAX_LEAD_WORDS = 3;

    /** @var list<string> */
    private const STOP_WORDS = [
        'ante', 'bajo', 'cabe', 'como', 'con', 'contra', 'desde', 'donde', 'entre', 'hacia', 'hasta', 'para',
        'pero', 'por', 'porque', 'segun', 'sin', 'sobre', 'tras', 'aun', 'aunque', 'cada', 'cual', 'cuando',
        'cuanto', 'cuantos', 'cuantas', 'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas',
        'aquel', 'aquella', 'aquellos', 'aquellas', 'algo', 'alguien', 'alguno', 'alguna', 'algunos', 'algunas',
        'muy', 'mas', 'menos', 'mucho', 'muchos', 'muchas', 'poco', 'pocos', 'pocas', 'todo', 'todos', 'todas',
        'otro', 'otra', 'otros', 'otras', 'solo', 'sola', 'solos', 'solamente', 'tambien', 'ademas', 'aqui',
        'alli', 'aun', 'bien', 'casi', 'como', 'cual', 'cuando', 'debe', 'deben', 'dice', 'dijo', 'dice',
        'donde', 'dos', 'entre', 'era', 'eran', 'es', 'esa', 'ese', 'eso', 'esta', 'estan', 'este', 'esto',
        'estos', 'fue', 'fueron', 'ha', 'han', 'hay', 'hoy', 'las', 'los', 'les', 'le', 'lo', 'la', 'el',
        'del', 'al', 'un', 'una', 'unos', 'unas', 'y', 'o', 'u', 'e', 'ni', 'que', 'quien', 'quienes', 'se',
        'si', 'sin', 'sobre', 'su', 'sus', 'ser', 'son', 'tan', 'tanto', 'te', 'ti', 'tu', 'tus', 'ya', 'vos',
        'nos', 'nosotros', 'ellos', 'ellas', 'usted', 'ustedes', 'contra', 'hacia', 'tras', 'ante', 'bajo',
        'noticia', 'noticias', 'informa', 'informan', 'informacion', 'segun', 'nuevo', 'nueva', 'nuevos', 'nuevas',
        'ultimo', 'ultima', 'ultimos', 'ultimas', 'tras', 'ante', 'tras', 'cada', 'tras', 'puede', 'pueden',
    ];

    /**
     * @return list<string>
     */
    public function suggestForArticle(Article $article): array
    {
        $names = [];

        $category = $article->getCategory();
        if ($category !== null) {
            $names[] = $category->getName();
            $parent = $category->getParent();
            if ($parent !== null) {
                $names[] = $parent->getName();
            }
        }

        $names = array_merge($names, $this->extractKeywords($article->getTitle(), self::MAX_TITLE_WORDS));

        if ($article->getSubtitle() !== null && $article->getSubtitle() !== '') {
            $names = array_merge($names, $this->extractKeywords($article->getSubtitle(), 2));
        }

        if ($article->getLead() !== null && $article->getLead() !== '') {
            $names = array_merge($names, $this->extractKeywords($article->getLead(), self::MAX_LEAD_WORDS));
        }

        return $this->normalizeNames($names);
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function normalizeNames(array $names): array
    {
        $seen = [];
        $normalized = [];

        foreach ($names as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            if (strlen($name) > 120) {
                $name = substr($name, 0, 120);
            }

            $key = mb_strtolower($name, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $normalized[] = $name;

            if (count($normalized) >= self::MAX_TAGS) {
                break;
            }
        }

        return $normalized;
    }

    /**
     * @return list<string>
     */
    private function extractKeywords(string $text, int $max): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower($text, 'UTF-8');
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?? [];
        $keywords = [];

        foreach ($parts as $part) {
            if (mb_strlen($part, 'UTF-8') < self::MIN_WORD_LENGTH) {
                continue;
            }

            if (in_array($part, self::STOP_WORDS, true)) {
                continue;
            }

            if (preg_match('/^\d+$/', $part) === 1) {
                continue;
            }

            $display = mb_convert_case($part, MB_CASE_TITLE, 'UTF-8');
            $key = mb_strtolower($display, 'UTF-8');
            if (isset($keywords[$key])) {
                continue;
            }

            $keywords[$key] = $display;
            if (count($keywords) >= $max) {
                break;
            }
        }

        return array_values($keywords);
    }
}
