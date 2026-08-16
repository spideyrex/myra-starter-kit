<?php

namespace App\Services\Ai\Compiler;

use App\Admin\QueryBuilder\FieldSet;
use App\Admin\QueryBuilder\QueryBuilderException;
use App\Admin\QueryBuilder\RuleTree;
use App\Admin\Views\ViewShape;
use App\Services\Ai\AiCompileException;
use App\Services\Ai\AiService;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Natural language in, a VALIDATED RuleTree out.
 *
 * The model's only legal output is the existing {conjunction, rules, groups}
 * JSON. It is parsed by RuleTree::parse() — the same and only entry point a
 * hand-typed tree takes. RuleTree has a private constructor, so this method's
 * `: RuleTree` return type is the guarantee at the type level: there is no
 * expressible path from model text to a query that skips validation.
 */
final class RuleTreeCompiler
{
    public const MAX_PROMPT = 500;

    public function __construct(private AiService $ai) {}

    /**
     * @throws QueryBuilderException|AiCompileException
     */
    public function compile(string $prompt, FieldSet $set, ?Authenticatable $user): RuleTree
    {
        $prompt = trim($prompt);
        $max = (int) config('myra.ai.max_prompt', self::MAX_PROMPT);

        if ($prompt === '' || mb_strlen($prompt) > $max) {
            throw AiCompileException::make('assistant.errors.promptLength');
        }

        // The ONLY context the provider receives: field names, types, allowed
        // operator strings, option values. toClientSchema() omits column(), so
        // no SQL identifier leaves the box — and no rows do either.
        $vocabulary = $set->visibleTo($user)->toClientSchema($user);
        $system = $this->systemPrompt($vocabulary);

        try {
            return $this->attempt($system, $prompt, $set, $user);
        } catch (QueryBuilderException|AiCompileException $first) {
            // Exactly ONE repair retry. Safety does not depend on it: parse()
            // is downstream of every path and does not care where JSON came from.
            $repair = $prompt."\n\nYour previous answer was rejected: ".$first->getMessage()
                ."\nReply with corrected JSON only.";

            try {
                return $this->attempt($system, $repair, $set, $user);
            } catch (QueryBuilderException|AiCompileException) {
                throw $first;
            }
        }
    }

    /** @throws QueryBuilderException|AiCompileException */
    private function attempt(string $system, string $userPrompt, FieldSet $set, ?Authenticatable $user): RuleTree
    {
        try {
            $raw = $this->ai->complete($system, $userPrompt);
        } catch (QueryBuilderException|AiCompileException $e) {
            throw $e;
        } catch (Throwable) {
            throw AiCompileException::make('assistant.errors.providerDown');
        }

        AiOutputGuard::assertNoSql($raw);          // tripwire, NOT the defence
        $decoded = JsonEnvelope::decode($raw);
        ViewShape::assertQueryTree($decoded, 'filter');   // cheap bounded shape gate

        return RuleTree::parse($decoded, $set, $user);    // the ONLY path to SQL
    }

    /** @param  array<string,mixed>  $vocabulary */
    private function systemPrompt(array $vocabulary): string
    {
        $json = json_encode($vocabulary, JSON_UNESCAPED_SLASHES);

        return <<<PROMPT
        You translate a request into a filter tree for an admin table.

        Reply with ONE JSON object and nothing else. No prose, no code fences, no SQL.

        Shape:
        {"conjunction":"and"|"or","rules":[{"field":"<name>","operator":"<operator>","value":<value>}],"groups":[<same shape>]}

        Rules:
        - `field` MUST be one of the field names below. Never invent one.
        - `operator` MUST be one of that field's listed operators.
        - Operators is_filled, is_blank, is_true and is_false take value null.
        - between and date_between take a two-element array.
        - in, not_in, related_to and not_related_to take an array of values.
        - Every other operator takes a single string value.
        - For a field with an `options` list, values MUST come from that list.
        - Dates are ISO 8601 (YYYY-MM-DD).
        - Nest at most {$vocabulary['maxDepth']} levels and emit at most {$vocabulary['maxRules']} rules.
        - If the request cannot be expressed, reply {"conjunction":"and","rules":[],"groups":[]}.

        Available fields:
        {$json}
        PROMPT;
    }
}
