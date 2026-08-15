<?php

namespace App\Admin\Report;

use App\Admin\QueryBuilder\Operator;
use App\Admin\Report\Contracts\DrillResolver;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Builds the URL a segment navigates to. The tree it emits carries NO
 * authority: the target controller re-parses it through ITS OWN FieldSet via
 * the untouched HandlesQueryBuilder. A forged drill URL is validated exactly
 * like a hand-typed filter.
 */
final class DrillThrough implements DrillResolver
{
    /** @return array{url: string, params: array<string,string>}|null */
    public function forRow(ReportDefinition $definition, ReportRequest $request, ReportRow $row): ?array
    {
        if ($row->isOther) {
            return null;
        }

        $route = $this->option($definition, ['drillRoute', 'drillRouteName'], null);

        if (! is_string($route) || $route === '' || ! app('router')->has($route)) {
            return null;
        }

        $dimension = $request->dimension();
        $field = $dimension->drillField();

        if (! is_string($field) || $field === '') {
            return null;
        }

        $rule = $this->dimensionRule($dimension, $request, $row, $field);

        if ($rule === null) {
            return null;
        }

        $group = $this->and($request->filters()->toArray(), $rule);
        $group = $this->withPeriod($group, $definition, $request, $field);

        if ($this->ruleCount($group) > $definition->fieldSet()->maxRuleCount()) {
            return null;
        }

        $param = (string) ($this->option($definition, ['drillQueryParam'], 'query') ?: 'query');
        $routeParams = (array) ($this->option($definition, ['drillRouteParams'], []) ?: []);

        return [
            'url' => route($route, $routeParams),
            'params' => [$param => (string) json_encode($group)],
        ];
    }

    /** @return array<string,mixed>|null */
    private function dimensionRule(Dimension $dimension, ReportRequest $request, ReportRow $row, string $field): ?array
    {
        $operator = $dimension->drillOperator();

        $value = match ($operator->arity()) {
            0 => null,
            2 => $this->bucketRange($request->bucket(), $row->key),
            -1 => [$row->key],
            default => $row->key,
        };

        if ($operator->arity() === 2 && $value === null) {
            return null;
        }

        return ['field' => $field, 'operator' => $operator->value, 'value' => $value];
    }

    /**
     * The period itself, added only when the target whitelist declares the date
     * column — emitting a rule the target would reject is worse than omitting it.
     * Skipped when the dimension already drills on that column: the bucket rule
     * is strictly narrower.
     *
     * @param  array<string,mixed>  $group
     * @return array<string,mixed>
     */
    private function withPeriod(array $group, ReportDefinition $definition, ReportRequest $request, string $drillField): array
    {
        $column = (string) ($this->option($definition, ['dateColumnName', 'dateColumn'], 'created_at') ?: 'created_at');

        if ($column === $drillField || $definition->fieldSet()->field($column) === null) {
            return $group;
        }

        $period = $request->period();

        return $this->and($group, [
            'field' => $column,
            'operator' => Operator::DateBetween->value,
            'value' => [
                $period->start->format('Y-m-d'),
                $period->end->subDay()->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * The calendar range one bucket key covers, inclusive on both ends — the
     * wire form DateBetween expects.
     *
     * @return array{0: string, 1: string}|null
     */
    private function bucketRange(?Bucket $bucket, string $key): ?array
    {
        if ($bucket === null) {
            return null;
        }

        $start = $this->bucketStart($bucket, $key);

        if ($start === null) {
            return null;
        }

        $end = $start->add(new \DateInterval($bucket->interval()))->subDay();

        // Sub-day buckets (Hour) span less than a day, and bucketStart() already
        // truncated to the day, so subDay() would emit an inverted range.
        if ($end->lessThan($start)) {
            $end = $start;
        }

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    private function bucketStart(Bucket $bucket, string $key): ?CarbonImmutable
    {
        try {
            return match ($bucket) {
                Bucket::Hour => CarbonImmutable::createFromFormat('Y-m-d H:i', $key)->startOfDay(),
                Bucket::Day => CarbonImmutable::createFromFormat('Y-m-d', $key)->startOfDay(),
                // ISO weeks. sqlite's %W is Sunday-based, so a sqlite week drill can
                // sit one day off; documented, and exact on pgsql/mysql.
                Bucket::Week => $this->isoWeekStart($key),
                Bucket::Month => CarbonImmutable::createFromFormat('Y-m-d', $key.'-01')->startOfDay(),
                Bucket::Quarter => $this->quarterStart($key),
                Bucket::Year => CarbonImmutable::createFromFormat('Y-m-d', $key.'-01-01')->startOfDay(),
            };
        } catch (Throwable) {
            return null;
        }
    }

    private function isoWeekStart(string $key): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-W(\d{1,2})$/', $key, $m)) {
            return null;
        }

        return CarbonImmutable::now()->startOfDay()->setISODate((int) $m[1], max(1, (int) $m[2]), 1);
    }

    private function quarterStart(string $key): ?CarbonImmutable
    {
        if (! preg_match('/^(\d{4})-Q([1-4])$/', $key, $m)) {
            return null;
        }

        return CarbonImmutable::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1, 0, 0, 0);
    }

    /**
     * @param  array<string,mixed>  $group
     * @param  array<string,mixed>  $rule
     * @return array<string,mixed>
     */
    private function and(array $group, array $rule): array
    {
        $normalised = [
            'conjunction' => 'and',
            'rules' => array_values((array) ($group['rules'] ?? [])),
            'groups' => array_values((array) ($group['groups'] ?? [])),
        ];

        // An OR root cannot absorb an AND rule: nest it instead of widening it.
        if (($group['conjunction'] ?? 'and') === 'or' && ($normalised['rules'] || $normalised['groups'])) {
            return ['conjunction' => 'and', 'rules' => [$rule], 'groups' => [$group]];
        }

        $normalised['rules'][] = $rule;

        return $normalised;
    }

    /** @param array<string,mixed> $group */
    private function ruleCount(array $group): int
    {
        $count = count((array) ($group['rules'] ?? []));

        foreach ((array) ($group['groups'] ?? []) as $child) {
            $count += is_array($child) ? $this->ruleCount($child) : 0;
        }

        return $count;
    }

    /**
     * Seam: the drill target getters are declared by the report bundle. Probing
     * the candidate names keeps a naming drift between bundles from turning into
     * a fatal at merge.
     *
     * @param  string[]  $candidates
     */
    private function option(ReportDefinition $definition, array $candidates, mixed $default): mixed
    {
        foreach ($candidates as $method) {
            if (method_exists($definition, $method)) {
                return $definition->{$method}();
            }
        }

        return $default;
    }
}
