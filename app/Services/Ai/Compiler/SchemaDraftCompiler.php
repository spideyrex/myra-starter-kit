<?php

namespace App\Services\Ai\Compiler;

use App\Services\Ai\AiCompileException;
use App\Services\Ai\AiService;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Returns a DRAFT, never a file. Nothing is written, nothing is eval'd, nothing
 * is migrated — the output is a make:myra-resource invocation a developer reads
 * and runs themselves.
 */
final class SchemaDraftCompiler
{
    public const TYPES = ['text', 'number', 'boolean', 'date', 'select', 'relation'];

    public const MAX_FIELDS = 24;

    private const IDENTIFIER = '/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i';

    private const MODEL = '/^[A-Z][A-Za-z0-9]{0,39}$/';

    public function __construct(private AiService $ai) {}

    /**
     * @return array{model:string, fields:array<int,array{name:string,type:string}>, command:string}
     *
     * @throws AiCompileException
     */
    public function draft(string $prompt, ?Authenticatable $user): array
    {
        $prompt = trim($prompt);
        $max = (int) config('myra.ai.max_prompt', RuleTreeCompiler::MAX_PROMPT);

        if ($prompt === '' || mb_strlen($prompt) > $max) {
            throw AiCompileException::make('assistant.errors.promptLength');
        }

        try {
            $raw = $this->ai->complete($this->systemPrompt(), $prompt);
        } catch (AiCompileException $e) {
            throw $e;
        } catch (Throwable) {
            throw AiCompileException::make('assistant.errors.providerDown');
        }

        // No AiOutputGuard here: `select` is a legal field TYPE, so the SQL
        // tripwire would false-positive. The closed TYPES list and the
        // identifier regex below are the defence, and nothing is ever executed.
        $decoded = JsonEnvelope::decode($raw);

        $model = is_string($decoded['model'] ?? null) ? trim($decoded['model']) : '';
        if (preg_match(self::MODEL, $model) !== 1) {
            throw AiCompileException::make('assistant.errors.couldNotUnderstand');
        }

        $fields = [];
        $seen = [];

        foreach ((array) ($decoded['fields'] ?? []) as $field) {
            if (count($fields) >= self::MAX_FIELDS) {
                break;
            }

            if (! is_array($field)) {
                continue;
            }

            $name = is_string($field['name'] ?? null) ? trim($field['name']) : '';
            $type = is_string($field['type'] ?? null) ? strtolower(trim($field['type'])) : '';

            // A miss DROPS the field. Nothing is coerced into the allow-list.
            if (preg_match(self::IDENTIFIER, $name) !== 1
                || ! in_array($type, self::TYPES, true)
                || isset($seen[$name])) {
                continue;
            }

            $seen[$name] = true;
            $fields[] = ['name' => $name, 'type' => $type];
        }

        if ($fields === []) {
            throw AiCompileException::make('assistant.errors.couldNotUnderstand');
        }

        return [
            'model' => $model,
            'fields' => $fields,
            'command' => $this->command($model, $fields),
        ];
    }

    /** Escaped for DISPLAY only. Nothing here is executed. */
    private function command(string $model, array $fields): string
    {
        $pairs = implode(',', array_map(
            static fn (array $f) => $f['name'].':'.$f['type'],
            $fields,
        ));

        return 'php artisan make:myra-resource '.$model.' --fields="'.$pairs.'"';
    }

    private function systemPrompt(): string
    {
        $types = implode('|', self::TYPES);

        return <<<PROMPT
        You draft an admin resource schema.

        Reply with ONE JSON object and nothing else. No prose, no code fences, no SQL, no migrations.

        Shape:
        {"model":"StudlyCaseSingular","fields":[{"name":"snake_case","type":"{$types}"}]}

        Rules:
        - `model` is a singular StudlyCase class name, letters and digits only.
        - `name` is snake_case, starting with a letter or underscore.
        - `type` MUST be one of: {$types}.
        - Do not include id, created_at, updated_at or deleted_at.
        - At most 24 fields.
        PROMPT;
    }
}
