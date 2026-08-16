<?php

namespace Tests\Unit\Dashboard;

use App\Admin\Dashboard\LayoutShape;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LayoutShapeTest extends TestCase
{
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'version' => 1,
            'entries' => [['key' => 'total_users', 'order' => 0, 'colSpan' => 2, 'rowSpan' => 1, 'hidden' => false]],
            'instances' => [],
        ], $overrides);
    }

    private function assertRejected(mixed $payload, string $why): void
    {
        try {
            LayoutShape::assert($payload, 'payload');
            $this->fail("LayoutShape accepted {$why}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('payload', $e->errors());
        }
    }

    public function test_a_well_formed_payload_normalises(): void
    {
        $out = LayoutShape::assert($this->valid(), 'payload');

        $this->assertSame(1, $out['version']);
        $this->assertSame('total_users', $out['entries'][0]['key']);
        $this->assertSame(2, $out['entries'][0]['colSpan']);
        $this->assertSame([], $out['instances']);
    }

    public function test_a_per_breakpoint_col_span_survives(): void
    {
        $out = LayoutShape::assert($this->valid([
            'entries' => [['key' => 'a', 'order' => 0, 'colSpan' => ['sm' => 2, 'lg' => 4]]],
        ]), 'payload');

        $this->assertSame(['sm' => 2, 'lg' => 4], $out['entries'][0]['colSpan']);
    }

    public function test_it_rejects_a_payload_over_the_byte_cap(): void
    {
        $entries = [];
        for ($i = 0; $i < 80; $i++) {
            $entries[] = ['key' => str_repeat('a', 60).$i, 'order' => 0, 'colSpan' => 1, 'rowSpan' => 1, 'hidden' => false];
        }

        $this->assertGreaterThan(
            LayoutShape::MAX_BYTES,
            strlen((string) json_encode(['version' => 1, 'entries' => $entries, 'instances' => []])),
        );

        $this->assertRejected(['version' => 1, 'entries' => $entries, 'instances' => []], 'an oversized payload');
    }

    public function test_it_rejects_too_many_entries(): void
    {
        $entries = [];
        for ($i = 0; $i <= LayoutShape::MAX_ENTRIES; $i++) {
            $entries[] = ['key' => 'w'.$i, 'order' => 0];
        }

        $this->assertRejected(['version' => 1, 'entries' => $entries, 'instances' => []], 'too many entries');
    }

    public function test_it_rejects_too_many_instances(): void
    {
        $instances = [];
        for ($i = 0; $i <= LayoutShape::MAX_INSTANCES; $i++) {
            $instances[] = ['key' => 'c#'.$i, 'catalogue' => 'c', 'binding' => []];
        }

        $this->assertRejected(['version' => 1, 'entries' => [], 'instances' => $instances], 'too many instances');
    }

    public function test_it_rejects_an_unexpected_top_level_key(): void
    {
        $this->assertRejected(
            $this->valid(['name' => 'mine']),
            'an unknown top-level key',
        );
    }

    public function test_it_rejects_a_missing_top_level_key(): void
    {
        $this->assertRejected(['version' => 1, 'entries' => []], 'a missing top-level key');
    }

    public function test_it_rejects_a_non_list_entries_array(): void
    {
        $this->assertRejected($this->valid(['entries' => ['a' => ['key' => 'x', 'order' => 0]]]), 'a keyed entries map');
    }

    public function test_it_rejects_a_version_other_than_one(): void
    {
        $this->assertRejected($this->valid(['version' => 2]), 'version 2');
        $this->assertRejected($this->valid(['version' => '1']), 'a string version');
    }

    public function test_it_rejects_an_out_of_range_span(): void
    {
        $this->assertRejected($this->valid(['entries' => [['key' => 'a', 'order' => 0, 'colSpan' => 99]]]), 'colSpan 99');
        $this->assertRejected($this->valid(['entries' => [['key' => 'a', 'order' => 0, 'colSpan' => 0]]]), 'colSpan 0');
        $this->assertRejected($this->valid(['entries' => [['key' => 'a', 'order' => 0, 'rowSpan' => 9]]]), 'rowSpan 9');
        $this->assertRejected($this->valid(['entries' => [['key' => 'a', 'order' => 300]]]), 'order 300');
    }

    public function test_it_rejects_an_unknown_breakpoint(): void
    {
        $this->assertRejected(
            $this->valid(['entries' => [['key' => 'a', 'order' => 0, 'colSpan' => ['md' => 2]]]]),
            'an unknown breakpoint',
        );
    }

    public function test_it_rejects_a_bad_key(): void
    {
        $this->assertRejected($this->valid(['entries' => [['key' => '../etc', 'order' => 0]]]), 'a traversal key');
        $this->assertRejected($this->valid(['entries' => [['key' => '', 'order' => 0]]]), 'an empty key');
        $this->assertRejected($this->valid(['entries' => [['key' => str_repeat('a', 200), 'order' => 0]]]), 'an over-long key');
    }

    public function test_it_rejects_an_unknown_entry_key(): void
    {
        $this->assertRejected(
            $this->valid(['entries' => [['key' => 'a', 'order' => 0, 'component' => 'X']]]),
            'an unknown entry field',
        );
    }

    public function test_it_rejects_a_non_boolean_hidden(): void
    {
        $this->assertRejected($this->valid(['entries' => [['key' => 'a', 'order' => 0, 'hidden' => 1]]]), 'hidden as 1');
    }

    public function test_it_rejects_an_instance_binding_that_is_not_an_assoc_array(): void
    {
        $this->assertRejected(
            $this->valid(['instances' => [['key' => 'c#1', 'catalogue' => 'c', 'binding' => ['a', 'b']]]]),
            'a list binding',
        );
    }

    public function test_it_rejects_an_over_long_instance_title(): void
    {
        $this->assertRejected(
            $this->valid(['instances' => [['key' => 'c#1', 'catalogue' => 'c', 'binding' => [], 'title' => str_repeat('t', 61)]]]),
            'a 61-character title',
        );
    }

    public function test_a_valid_instance_keeps_its_binding(): void
    {
        $out = LayoutShape::assert($this->valid(['instances' => [[
            'key' => 'c#1', 'catalogue' => 'c',
            'binding' => ['dimension' => 'status', 'measures' => ['signups'], 'limit' => 5],
            'title' => 'By status', 'chartType' => 'bar', 'order' => 3,
        ]]]), 'payload');

        $this->assertSame('status', $out['instances'][0]['binding']['dimension']);
        $this->assertSame(['signups'], $out['instances'][0]['binding']['measures']);
        $this->assertSame('By status', $out['instances'][0]['title']);
    }
}
