<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class AnthropicProvider implements AiProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $model = 'claude-sonnet-4-5-20250929',
        private float $temperature = 0.7,
        private int $maxTokens = 2048,
    ) {}

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => $this->temperature,
        ]);

        $response->throw();

        return $response->json('content.0.text', '');
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): void
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->withOptions(['stream' => true])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => $this->temperature,
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $json = json_decode(substr($line, 6), true);
                if (!$json) {
                    continue;
                }

                if (($json['type'] ?? '') === 'content_block_delta') {
                    $content = $json['delta']['text'] ?? '';
                    if ($content !== '') {
                        $onChunk($content);
                    }
                }
            }
        }
    }

    public function testConnection(): bool
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 10,
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
            ],
        ]);

        return $response->successful();
    }
}
