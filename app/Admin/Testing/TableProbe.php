<?php

namespace App\Admin\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * Assertions about a table the SERVER actually produced. Everything is read from
 * the real Inertia prop, so a probe can never pass against a payload the browser
 * would not receive.
 *
 * Tolerates both shapes in the wild: the resource shape (data/links/meta) and a
 * raw paginator handed straight to Inertia (meta keys at the top level).
 */
final class TableProbe
{
    /** Query params that describe a position, not a query. */
    private const VOLATILE = ['page', 'cursor'];

    /** Columns that, when present, identify the owner of a row. */
    private const OWNER_KEYS = ['created_by', 'user_id', 'owner_id'];

    private function __construct(
        private readonly TestCase $test,
        private readonly TestResponse $testResponse,
        private readonly string $prop,
    ) {}

    public static function make(TestCase $test, TestResponse $r, string $prop): self
    {
        return new self($test, $r, $prop);
    }

    // --- readers -----------------------------------------------------------

    public function response(): TestResponse
    {
        return $this->testResponse;
    }

    /** @return array<int,array<string,mixed>> */
    public function data(): array
    {
        $payload = $this->payload();

        Assert::assertArrayHasKey(
            'data',
            $payload,
            "Inertia prop [{$this->prop}] is not a table payload — it has no `data` array.",
        );

        return array_values((array) $payload['data']);
    }

    public function meta(): array
    {
        $payload = $this->payload();

        return (array) ($payload['meta'] ?? $payload);
    }

    public function links(): array
    {
        return (array) ($this->payload()['links'] ?? []);
    }

    // --- record assertions -------------------------------------------------

    public function assertCanSeeRecords(iterable $records): self
    {
        $visible = $this->ids();

        foreach ($records as $record) {
            Assert::assertContains(
                $this->keyOf($record),
                $visible,
                "Record [{$this->keyOf($record)}] is missing from prop [{$this->prop}].",
            );
        }

        return $this;
    }

    public function assertCanNotSeeRecords(iterable $records): self
    {
        $visible = $this->ids();

        foreach ($records as $record) {
            Assert::assertNotContains(
                $this->keyOf($record),
                $visible,
                "Record [{$this->keyOf($record)}] leaked into prop [{$this->prop}].",
            );
        }

        return $this;
    }

    public function assertCountRecords(int $count): self
    {
        Assert::assertCount($count, $this->data(), "Prop [{$this->prop}] row count.");

        return $this;
    }

    public function assertTotal(int $total): self
    {
        Assert::assertSame($total, (int) ($this->meta()['total'] ?? -1), 'Paginator total.');

        return $this;
    }

    // --- column assertions -------------------------------------------------

    public function assertColumnExists(string $key): self
    {
        $rows = $this->data();

        Assert::assertNotEmpty($rows, "Prop [{$this->prop}] has no rows, so no column can be proven.");
        Assert::assertArrayHasKey($key, $rows[0], "Column [{$key}] is not in the payload.");

        return $this;
    }

    /** A sensitive attribute must not be in the payload at all. */
    public function assertColumnDoesNotLeak(string $key): self
    {
        foreach ($this->data() as $row) {
            Assert::assertArrayNotHasKey($key, $row, "Column [{$key}] leaked into prop [{$this->prop}].");
        }

        return $this;
    }

    public function assertColumnValue(string $key, mixed $value, Model $record): self
    {
        $row = $this->rowFor($record);

        Assert::assertArrayHasKey($key, $row, "Column [{$key}] is not in the payload.");
        Assert::assertSame($value, $row[$key], "Column [{$key}] for record [{$record->getKey()}].");

        return $this;
    }

    public function assertSortedBy(string $column, string $direction = 'asc'): self
    {
        $values = array_map(fn (array $row) => $row[$column] ?? null, $this->data());
        $sorted = $values;

        $direction === 'desc' ? rsort($sorted) : sort($sorted);

        Assert::assertSame($sorted, $values, "Rows are not ordered by [{$column} {$direction}].");

        return $this;
    }

    /** A rejected sort must leave the order EXACTLY as the unsorted request produced it. */
    public function assertSortRejected(string $column): self
    {
        $baseline = $this->ids();
        $attempt = $this->request(['sort' => $column])->ids();

        Assert::assertSame(
            $baseline,
            $attempt,
            "Sorting by [{$column}] changed the row order — it is not being rejected by the whitelist.",
        );

        return $this;
    }

    public function assertPerPageCapped(int $requested, int $actual = 100): self
    {
        $meta = $this->request(['per_page' => $requested])->meta();

        Assert::assertSame($actual, (int) ($meta['per_page'] ?? -1), "per_page={$requested} was not capped.");

        return $this;
    }

    public function assertPaginationMode(string $mode): self
    {
        Assert::assertSame($mode, $this->mode(), 'Pagination mode.');

        return $this;
    }

    public function assertHasNextPage(): self
    {
        if ($this->mode() === 'cursor') {
            Assert::assertNotNull($this->links()['next'] ?? null, 'Cursor payload has no next link.');

            return $this;
        }

        $meta = $this->meta();
        Assert::assertGreaterThan(
            (int) ($meta['current_page'] ?? 1),
            (int) ($meta['last_page'] ?? 1),
            'There is no next page.',
        );

        return $this;
    }

    /** Whole-dataset summaries arrive in a sibling `summaries` prop. */
    public function assertSummary(string $column, mixed $value): self
    {
        $summaries = (array) InertiaProps::normalise(InertiaProps::get($this->testResponse, 'summaries'));

        Assert::assertArrayHasKey($column, $summaries, "No server summary for column [{$column}].");
        Assert::assertEquals($value, $summaries[$column], "Summary for column [{$column}].");

        return $this;
    }

    /**
     * Saved views need a server-side view list on the page; without it the table
     * has no table key to store against and the views menu silently disappears.
     */
    public function assertSavedViewsEnabled(): self
    {
        $props = InertiaProps::of($this->testResponse);

        Assert::assertArrayHasKey(
            'savedViews',
            $props,
            'This page ships no `savedViews` prop, so its table cannot persist a saved view.',
        );
        Assert::assertIsArray(InertiaProps::normalise($props['savedViews']), 'The `savedViews` prop is not a list.');

        return $this;
    }

    /** Every row that exposes an owner must be owned by the acting user. */
    public function assertScopedToActor(): self
    {
        $rows = $this->data();
        Assert::assertNotEmpty($rows, "Prop [{$this->prop}] has no rows, so scoping cannot be proven.");

        $ownerKey = null;
        foreach (self::OWNER_KEYS as $candidate) {
            if (array_key_exists($candidate, $rows[0])) {
                $ownerKey = $candidate;
                break;
            }
        }

        Assert::assertNotNull(
            $ownerKey,
            "Prop [{$this->prop}] exposes no ownership column (" . implode(', ', self::OWNER_KEYS)
            . '), so scoping cannot be proven from props.',
        );

        $actor = Auth::id();

        foreach ($rows as $row) {
            Assert::assertSame(
                $actor,
                $row[$ownerKey],
                "Row [{$row['id']}] is owned by [{$row[$ownerKey]}], not by the acting user [{$actor}].",
            );
        }

        return $this;
    }

    /** Re-issues the same request under a query listener. */
    public function assertQueryCount(int $max): self
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });

        $this->request([]);

        Assert::assertLessThanOrEqual($max, $count, "The page issued {$count} queries (max {$max}).");

        return $this;
    }

    // --- navigation --------------------------------------------------------

    public function search(string $term): self
    {
        return $this->request(['search' => $term]);
    }

    public function sort(string $column, string $direction = 'asc'): self
    {
        return $this->request(['sort' => $column, 'direction' => $direction]);
    }

    public function page(int $n): self
    {
        return $this->request(['page' => $n]);
    }

    public function cursor(string $cursor): self
    {
        return $this->request(['cursor' => $cursor]);
    }

    // --- internals ---------------------------------------------------------

    private function payload(): array
    {
        $value = InertiaProps::normalise(InertiaProps::get($this->testResponse, $this->prop));

        Assert::assertIsArray($value, "Inertia prop [{$this->prop}] is not an array.");

        return $value;
    }

    private function mode(): string
    {
        return (string) ($this->meta()['mode'] ?? 'length-aware');
    }

    /** @return array<int,mixed> */
    private function ids(): array
    {
        return array_map(fn (array $row) => $row['id'] ?? null, $this->data());
    }

    private function keyOf(Model|int|string $record): mixed
    {
        return $record instanceof Model ? $record->getKey() : $record;
    }

    private function rowFor(Model $record): array
    {
        foreach ($this->data() as $row) {
            if (($row['id'] ?? null) == $record->getKey()) {
                return $row;
            }
        }

        Assert::fail("Record [{$record->getKey()}] is not in prop [{$this->prop}].");
    }

    /** Replays the page URL with the given params merged in. */
    private function request(array $params): self
    {
        $url = (string) ($this->testResponse->viewData('page')['url'] ?? '/');
        [$path, $queryString] = array_pad(explode('?', $url, 2), 2, '');

        parse_str($queryString, $existing);

        // A position param from the previous request must never survive a new query.
        foreach (self::VOLATILE as $key) {
            if (! array_key_exists($key, $params)) {
                unset($existing[$key]);
            }
        }

        $merged = array_filter(array_merge($existing, $params), fn ($v) => $v !== null && $v !== '');
        $next = $path . ($merged === [] ? '' : '?' . http_build_query($merged));

        return new self($this->test, $this->test->get($next), $this->prop);
    }
}
