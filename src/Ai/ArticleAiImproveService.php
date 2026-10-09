<?php

namespace App\Ai;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ArticleAiImproveService
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        private readonly OpenaiApiKeyResolver $apiKeyResolver,
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * @return array{title: string, subtitle: string, lead: string, bodyHtml: string}
     */
    public function improve(
        SiteContext $context,
        string $title,
        string $subtitle,
        string $lead,
        string $bodyHtml,
    ): array {
        $ai = $this->settingsReader->getAiConfig($context);
        $apiKey = $this->apiKeyResolver->resolve($context);
        if ($apiKey === '') {
            throw new \RuntimeException('No hay API key de OpenAI configurada.');
        }

        $title = trim($title);
        $subtitle = trim($subtitle);
        $lead = trim($lead);
        $bodyHtml = trim($bodyHtml);
        if ($title === '' && $this->plainText($bodyHtml) === '') {
            throw new \InvalidArgumentException('Escribe al menos un título o el cuerpo antes de mejorar con IA.');
        }

        $userPrompt = sprintf(
            "Periódico: %s\n\nTítulo actual:\n%s\n\nSubtítulo actual:\n%s\n\nEntradilla actual:\n%s\n\nCuerpo HTML actual:\n%s",
            $context->name,
            $title !== '' ? $title : '(vacío)',
            $subtitle !== '' ? $subtitle : '(vacío)',
            $lead !== '' ? $lead : '(vacío)',
            $bodyHtml !== '' ? $bodyHtml : '(vacío)',
        );

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $ai->model !== '' ? $ai->model : 'gpt-4o-mini',
                'temperature' => 0.4,
                'max_tokens' => 4000,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $ai->resolvedArticleImprovePrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt,
                    ],
                ],
            ],
            'timeout' => 90,
        ]);

        $payload = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $content = $payload['choices'][0]['message']['content'] ?? '';
        if (!\is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Respuesta vacía de OpenAI.');
        }

        $parsed = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!\is_array($parsed)) {
            throw new \RuntimeException('La IA no devolvió JSON válido.');
        }

        $improvedTitle = trim((string) ($parsed['title'] ?? ''));
        $improvedSubtitle = trim((string) ($parsed['subtitle'] ?? ''));
        $improvedLead = trim((string) ($parsed['lead'] ?? ''));
        $improvedBody = trim((string) ($parsed['bodyHtml'] ?? $parsed['body'] ?? ''));

        if ($improvedTitle === '' && $this->plainText($improvedBody) === '') {
            throw new \RuntimeException('La IA no devolvió título ni cuerpo utilizables.');
        }

        return [
            'title' => $improvedTitle !== '' ? $improvedTitle : $title,
            'subtitle' => $improvedSubtitle,
            'lead' => $improvedLead,
            'bodyHtml' => $improvedBody !== '' ? $improvedBody : $bodyHtml,
        ];
    }

    private function plainText(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
