<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;

class OllamaProvider implements AiProviderInterface
{
    public function __construct(
        private string $model = 'llama3.1',
        private ?string $baseUrl = null,
        private float $temperature = 0.7,
        private int $maxTokens = 2048,
    ) {}

    private function url(string $path): string
    {
        $base = rtrim($this->baseUrl ?: 'http://localhost:11434', '/');
        return $base . $path;
    }

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $response = Http::timeout(120)->post($this->url('/api/chat'), [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'stream' => false,
            'options' => [
                'temperature' => $this->temperature,
                'num_predict' => $this->maxTokens,
            ],
        ]);

        $response->throw();

        return $response->json('message.content', '');
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): void
    {
        $response = Http::withOptions(['stream' => true])
            ->timeout(120)
            ->post($this->url('/api/chat'), [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'stream' => true,
                'options' => [
                    'temperature' => $this->temperature,
                    'num_predict' => $this->maxTokens,
                ],
            ]);

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '') {
                    continue;
                }

                $json = json_decode($line, true);
                if (!$json) {
                    continue;
                }

                $content = $json['message']['content'] ?? '';
                if ($content !== '') {
                    $onChunk($content);
                }

                if ($json['done'] ?? false) {
                    return;
                }
            }
        }
    }

    public function testConnection(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->url('/api/tags'));
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
