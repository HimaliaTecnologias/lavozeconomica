<?php

namespace App\Core\Site\Dto;

final readonly class AiConfig
{
    public const DEFAULT_ARTICLE_IMPROVE_PROMPT = <<<'PROMPT'
Eres un redactor senior de un periódico digital en español. Mejora la noticia que te pasan sin cambiar los hechos ni inventar datos, citas o cifras.

Objetivos:
- Clarificar y potenciar el titular (claro, informativo, sin clickbait engañoso).
- Mejorar subtítulo y entradilla si aportan valor; si están vacíos, propón unos coherentes.
- Reescribir el cuerpo con estilo periodístico: párrafos cortos, lenguaje preciso, ortografía y sintaxis correctas.
- Conservar el HTML semántico básico del cuerpo (p, strong, em, a, ul, ol, li, h2, h3, blockquote). No uses scripts ni estilos inline.
- Mantén el tono del medio local y el ámbito geográfico implícito en el texto.

Responde SOLO con JSON válido con exactamente estas claves:
{"title":"...","subtitle":"...","lead":"...","bodyHtml":"..."}
PROMPT;

    public function __construct(
        public string $openaiApiKey = '',
        public bool $hasOpenaiApiKey = false,
        public string $model = 'gpt-4o-mini',
        public bool $envKeyAvailable = false,
        public string $articleImprovePrompt = self::DEFAULT_ARTICLE_IMPROVE_PROMPT,
    ) {}

    public function isConfigured(): bool
    {
        return $this->hasOpenaiApiKey || $this->envKeyAvailable;
    }

    public function resolvedArticleImprovePrompt(): string
    {
        $prompt = trim($this->articleImprovePrompt);

        return $prompt !== '' ? $prompt : self::DEFAULT_ARTICLE_IMPROVE_PROMPT;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'hasOpenaiApiKey' => $this->hasOpenaiApiKey,
            'model' => $this->model,
            'envKeyAvailable' => $this->envKeyAvailable,
            'isConfigured' => $this->isConfigured(),
            'articleImprovePrompt' => $this->resolvedArticleImprovePrompt(),
        ];
    }
}
