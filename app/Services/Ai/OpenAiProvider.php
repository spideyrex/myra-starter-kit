<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $model = 'gpt-4o-mini',
        private ?string $baseUrl = null,
        private float $temperature = 0.7,
        private int $maxTokens = 2048,
    ) {}

    private function url(string $path): string
    {
        $base = rtrim($this->baseUrl ?: 'https://api.openai.com/v1', '/');
        return $base . $path;
    }

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post($this->url('/chat/completions'), [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
            ]);

        $response->throw();

        return $response->json('choices.0.message.content', '');
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): void
    {
        $response = Http::withToken($this->apiKey)
            ->withOptions(['stream' => true])
            ->timeout(60)
            ->post($this->url('/chat/completions'), [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => $this->temperature,
                'max_tokens' => $this->maxTokens,
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }

                if (str_starts_with($line, 'data: ')) {
                    $json = json_decode(substr($line, 6), true);
                    $content = $json['choices'][0]['delta']['content'] ?? '';
                    if ($content !== '') {
                        $onChunk($content);
                    }
                }
            }
        }
    }

    public function testConnection(): bool
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->get($this->url('/models'));

        return $response->successful();
    }
}
