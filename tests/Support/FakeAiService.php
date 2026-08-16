<?php

namespace Tests\Support;

use App\Services\Ai\AiService;

/**
 * A canned provider. Every AI test binds this into the container so no test ever
 * makes a network call, and so the exact system prompt handed to a provider can
 * be asserted on.
 */
class FakeAiService extends AiService
{
    /** @var string[] */
    public array $systemPrompts = [];

    /** @var string[] */
    public array $userPrompts = [];

    public int $calls = 0;

    /** @param string[] $replies consumed in order; the last one repeats */
    public function __construct(private array $replies = ['{}'], private bool $enabled = true) {}

    public function complete(string $systemPrompt, string $userPrompt): string
    {
        $this->systemPrompts[] = $systemPrompt;
        $this->userPrompts[] = $userPrompt;

        $reply = $this->replies[$this->calls] ?? end($this->replies);
        $this->calls++;

        return (string) $reply;
    }

    public function stream(string $systemPrompt, string $userPrompt, callable $onChunk): void
    {
        $onChunk($this->complete($systemPrompt, $userPrompt));
    }

    public function testConnection(): bool
    {
        return true;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
