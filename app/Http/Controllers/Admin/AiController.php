<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\AiService;
use App\Settings\AiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiController extends Controller
{
    public function updateSettings(Request $request): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
            'provider' => ['required', 'string', 'in:openai,anthropic,openrouter,ollama'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'model' => ['nullable', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:32000'],
        ]);

        $settings = app(AiSettings::class);

        $settings->enabled = $request->boolean('enabled');
        $settings->provider = $request->input('provider');

        // Only update API key if a new value was provided (not blank)
        if ($request->filled('api_key')) {
            $settings->api_key = $request->input('api_key');
        }

        $settings->model = $request->input('model') ?: null;
        $settings->base_url = $request->input('base_url') ?: null;
        $settings->temperature = (float) $request->input('temperature');
        $settings->max_tokens = (int) $request->input('max_tokens');

        $settings->save();

        cache()->forget('ai_enabled');

        return back()->with('success', 'AI settings updated successfully.');
    }

    public function testConnection(): JsonResponse
    {
        try {
            $service = app(AiService::class);

            if (!$service->isEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'AI features are disabled. Enable them first.',
                ]);
            }

            $success = $service->testConnection();

            return response()->json([
                'success' => $success,
                'message' => $success
                    ? 'Connection successful! Your AI provider is configured correctly.'
                    : 'Connection failed. Please check your API key and settings.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ]);
        }
    }

    public function assist(Request $request): StreamedResponse
    {
        // >>> MYRA v2.5 [D] START
        // An ungated billable LLM endpoint is a defect, but gating it outright
        // would break every editor on the live site the moment it deploys. The
        // flag defaults FALSE, so today's behaviour is byte-identical; `ai.use`
        // is seeded onto manager and editor so flipping it later is a no-op.
        if (config('myra.ai.gate_assist') === true) {
            Gate::authorize('ai.use');
        }
        // <<< MYRA v2.5 [D] END

        $request->validate([
            'action' => ['required', 'string', 'in:generate,improve,fix_grammar,make_shorter,make_longer,summarize,change_tone'],
            'text' => ['nullable', 'string', 'max:50000'],
            'prompt' => ['nullable', 'string', 'max:2000'],
            'tone' => ['nullable', 'string', 'in:professional,casual,friendly,formal'],
        ]);

        $service = app(AiService::class);

        if (!$service->isEnabled()) {
            return response()->stream(function () {
                echo "data: " . json_encode(['error' => 'AI features are disabled.']) . "\n\n";
                ob_flush();
                flush();
            }, 200, $this->sseHeaders());
        }

        $action = $request->input('action');
        $text = $request->input('text', '');
        $prompt = $request->input('prompt', '');
        $tone = $request->input('tone') ?? 'professional';

        $systemPrompt = $this->buildSystemPrompt($action, $tone);
        $userPrompt = $this->buildUserPrompt($action, $text, $prompt);

        return response()->stream(function () use ($service, $systemPrompt, $userPrompt) {
            try {
                $service->stream($systemPrompt, $userPrompt, function (string $chunk) {
                    echo "data: " . json_encode(['content' => $chunk]) . "\n\n";
                    if (ob_get_level()) {
                        ob_flush();
                    }
                    flush();
                });

                echo "data: [DONE]\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            } catch (\Throwable $e) {
                echo "data: " . json_encode(['error' => 'AI request failed: ' . $e->getMessage()]) . "\n\n";
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, 200, $this->sseHeaders());
    }

    private function sseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];
    }

    private function buildSystemPrompt(string $action, string $tone): string
    {
        $base = 'You are a writing assistant embedded in a rich text editor. Output clean HTML only — no markdown, no code fences, no explanations. Use standard HTML tags: <p>, <strong>, <em>, <ul>, <ol>, <li>, <h2>, <h3>, <blockquote>. Do not wrap output in ```html blocks.';

        return match ($action) {
            'generate' => $base . ' Generate content based on the user\'s prompt.',
            'improve' => $base . ' Improve the writing quality while preserving the original meaning and structure.',
            'fix_grammar' => $base . ' Fix grammar, spelling, and punctuation errors. Keep the original tone and meaning.',
            'make_shorter' => $base . ' Make the text more concise while preserving key information.',
            'make_longer' => $base . ' Expand the text with more detail while keeping it relevant and well-written.',
            'summarize' => $base . ' Summarize the text into a concise overview.',
            'change_tone' => $base . " Rewrite the text in a {$tone} tone while preserving the meaning.",
            default => $base,
        };
    }

    private function buildUserPrompt(string $action, string $text, string $prompt): string
    {
        if ($action === 'generate') {
            return $prompt ?: 'Write a paragraph about the topic.';
        }

        return $text;
    }
}
