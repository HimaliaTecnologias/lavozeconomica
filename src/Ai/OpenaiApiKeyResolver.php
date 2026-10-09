<?php

namespace App\Ai;

use App\Core\Site\SiteContext;
use App\Core\Site\SiteSettingsReader;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Resuelve la API key de OpenAI del periódico (central) o del entorno.
 */
final class OpenaiApiKeyResolver
{
    public function __construct(
        private readonly SiteSettingsReader $settingsReader,
        #[Autowire(env: 'string:default::OPENAI_API_KEY')]
        private readonly string $defaultOpenaiApiKey = '',
    ) {}

    public function resolve(SiteContext $context, string $moduleFallbackKey = ''): string
    {
        $central = $this->settingsReader->getAiConfig($context)->openaiApiKey;
        if ($central !== '') {
            return $central;
        }

        $moduleFallbackKey = trim($moduleFallbackKey);
        if ($moduleFallbackKey !== '') {
            return $moduleFallbackKey;
        }

        return trim($this->defaultOpenaiApiKey);
    }

    public function envKeyAvailable(): bool
    {
        return trim($this->defaultOpenaiApiKey) !== '';
    }
}
