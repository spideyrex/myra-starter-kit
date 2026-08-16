<?php

namespace App\Services\Ai\Compiler;

use App\Services\Ai\AiCompileException;
use JsonException;

/**
 * Extracts the one JSON object a model was asked for. Strict: no regex repair,
 * no best-effort salvage, no second guess about what the model "meant".
 */
final class JsonEnvelope
{
    public const MAX_BYTES = 8192;

    /**
     * @return array<string,mixed>
     *
     * @throws AiCompileException
     */
    public static function decode(string $raw, int $depth = 8): array
    {
        $max = (int) config('myra.ai.json_max_bytes', self::MAX_BYTES);

        if (strlen($raw) > $max) {
            throw AiCompileException::make('assistant.errors.couldNotUnderstand');
        }

        $s = trim($raw);

        // Strip ``` / ```json fences.
        if (str_starts_with($s, '```')) {
            $s = preg_replace('/^```[a-zA-Z0-9_-]*\s*/', '', $s) ?? $s;
            $end = strrpos($s, '```');
            if ($end !== false) {
                $s = substr($s, 0, $end);
            }
            $s = trim($s);
        }

        $first = strpos($s, '{');
        $last = strrpos($s, '}');

        if ($first === false || $last === false || $last <= $first) {
            throw AiCompileException::make('assistant.errors.couldNotUnderstand');
        }

        $s = substr($s, $first, $last - $first + 1);

        if (strlen($s) > $max) {
            throw AiCompileException::make('assistant.errors.couldNotUnderstand');
        }

        try {
            $decoded = json_decode($s, true, max(2, $depth), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw AiCompileException::make('assistant.errors.couldNotUnderstand');
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw AiCompileException::make('assistant.errors.couldNotUnderstand');
        }

        return $decoded;
    }
}
