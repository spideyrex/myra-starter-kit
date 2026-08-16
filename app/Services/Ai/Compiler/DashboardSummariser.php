<?php

namespace App\Services\Ai\Compiler;

use App\Admin\Report\ReportResult;
use App\Services\Ai\AiCompileException;
use App\Services\Ai\AiService;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Summarises what is ALREADY on the acting user's screen.
 *
 * The input is ReportResult aggregates — buckets, never source rows — produced
 * by the Gate-checked, ownership-scoped runner for THIS user. The controller
 * re-runs the batch server-side rather than trusting client-supplied numbers,
 * so a forged POST cannot get another tenant's figures narrated back.
 */
final class DashboardSummariser
{
    public const MAX_BUCKETS = 40;

    public const MAX_WIDGETS = 8;

    public function __construct(private AiService $ai) {}

    /**
     * @param  array<string, ReportResult>  $results
     *
     * @throws AiCompileException
     */
    public function summarise(array $results, string $locale, ?Authenticatable $user): string
    {
        if ($results === []) {
            throw AiCompileException::make('assistant.errors.couldNotUnderstand');
        }

        $payload = $this->payload($results);

        try {
            return trim($this->ai->complete($this->systemPrompt($locale), json_encode($payload, JSON_UNESCAPED_SLASHES)));
        } catch (AiCompileException $e) {
            throw $e;
        } catch (Throwable) {
            throw AiCompileException::make('assistant.errors.providerDown');
        }
    }

    /** @param  array<string, ReportResult>  $results */
    public function payload(array $results): array
    {
        $out = [];

        foreach (array_slice($results, 0, self::MAX_WIDGETS, true) as $slot => $result) {
            if (! $result instanceof ReportResult) {
                continue;
            }

            $out[(string) $slot] = $this->redact($result->toArray());
        }

        return $out;
    }

    /**
     * Whitelists key/label/values/deltas. DROPS `drill` — those URLs carry
     * filter params, and `state` — it carries the rule tree.
     */
    private function redact(array $result): array
    {
        $max = (int) config('myra.ai.summary_max_buckets', self::MAX_BUCKETS);

        $rows = [];

        foreach (array_slice((array) ($result['rows'] ?? []), 0, $max) as $row) {
            $rows[] = [
                'key' => (string) ($row['key'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'values' => (array) ($row['values'] ?? []),
                'deltas' => (array) ($row['deltas'] ?? []),
            ];
        }

        return [
            'report' => (string) ($result['report'] ?? ''),
            'period' => array_intersect_key((array) ($result['period'] ?? []), array_flip(['from', 'to', 'bucket'])),
            'measures' => array_values(array_map(
                static fn ($m) => (string) ($m['key'] ?? ''),
                (array) ($result['measures'] ?? []),
            )),
            'rows' => $rows,
            'totals' => (array) ($result['totals'] ?? []),
            'deltas' => (array) ($result['deltas'] ?? []),
            'truncated' => (bool) ($result['truncated'] ?? false),
        ];
    }

    private function systemPrompt(string $locale): string
    {
        $language = match ($locale) {
            'ms' => 'Bahasa Melayu',
            'zh' => 'Simplified Chinese',
            default => 'English',
        };

        return "You summarise an admin dashboard for the person looking at it.\n"
            ."The JSON below is already aggregated: buckets and totals, no personal records.\n"
            ."Write at most four short sentences in {$language}. Plain text, no markdown, no code.\n"
            .'State what moved and by how much. Do not invent numbers that are not present.';
    }
}
