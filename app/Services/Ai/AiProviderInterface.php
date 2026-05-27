<?php

namespace App\Services\Ai;

interface AiProviderInterface
{
    public function complete(string $systemPrompt, string $userPrompt): string;

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): void;

    public function testConnection(): bool;
}
