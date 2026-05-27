<?php

namespace App\Services\Ai;

use App\Settings\AiSettings;

class AiService
{
    private AiProviderInterface $provider;

    public function __construct(private AiSettings $settings)
    {
        $this->provider = $this->resolveProvider();
    }

    private function resolveProvider(): AiProviderInterface
    {
        return match ($this->settings->provider) {
            'anthropic' => new AnthropicProvider(
                apiKey: $this->settings->api_key ?? '',
                model: $this->settings->model ?: 'claude-sonnet-4-5-20250929',
                temperature: $this->settings->temperature,
                maxTokens: $this->settings->max_tokens,
            ),
            'openrouter' => new OpenRouterProvider(
                apiKey: $this->settings->api_key ?? '',
                model: $this->settings->model ?: 'openai/gpt-4o-mini',
                temperature: $this->settings->temperature,
                maxTokens: $this->settings->max_tokens,
            ),
            'ollama' => new OllamaProvider(
                model: $this->settings->model ?: 'llama3.1',
                baseUrl: $this->settings->base_url,
                temperature: $this->settings->temperature,
                maxTokens: $this->settings->max_tokens,
            ),
            default => new OpenAiProvider(
                apiKey: $this->settings->api_key ?? '',
                model: $this->settings->model ?: 'gpt-4o-mini',
                baseUrl: $this->settings->base_url,
                temperature: $this->settings->temperature,
                maxTokens: $this->settings->max_tokens,
            ),
        };
    }

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        return $this->provider->complete($systemPrompt, $userPrompt);
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): void
    {
        $this->provider->stream($systemPrompt, $userPrompt, $onChunk);
    }

    public function testConnection(): bool
    {
        return $this->provider->testConnection();
    }

    public function isEnabled(): bool
    {
        return $this->settings->enabled;
    }
}
