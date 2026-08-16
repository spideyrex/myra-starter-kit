<?php

namespace Tests\Unit\Dashboard;

use App\Admin\Dashboard\LayoutShape;
use Tests\TestCase;

/**
 * filter() is the READ path: throwing there would blank a dashboard, so a
 * malformed element is DROPPED. assert() stays the write path and still 422s.
 */
class LayoutShapeFilterTest extends TestCase
{
    private function assertShape(mixed $payload): array
    {
        $out = LayoutShape::filter($payload);

        $this->assertSame(['version', 'entries', 'instances'], array_keys($out));
        $this->assertSame(1, $out['version']);
        $this->assertTrue(array_is_list($out['entries']));
        $this->assertTrue(array_is_list($out['instances']));

        return $out;
    }

    public static function malformedProvider(): array
    {
        return [
            'null' => [null],
            'scalar' => ['nope'],
            'int' => [7],
            'empty' => [[]],
            'list' => [[1, 2, 3]],
            'wrong version' => [['version' => 9, 'entries' => [], 'instances' => []]],
            'entries not a list' => [['version' => 1, 'entries' => ['a' => 1], 'instances' => []]],
            'entries a string' => [['version' => 1, 'entries' => 'x', 'instances' => 'y']],
            'nested junk' => [['version' => 1, 'entries' => [[[]]], 'instances' => [[['x']]]]],
            'deep nulls' => [['version' => 1, 'entries' => [null, null], 'instances' => [null]]],
        ];
    }

    /** @dataProvider malformedProvider */
    public function test_it_never_throws_and_always_returns_the_three_key_shape(mixed $payload): void
    {
        $this->assertShape($payload);
    }

    public function test_it_keeps_the_well_formed_and_drops_the_rest(): void
    {
        $out = $this->assertShape([
            'version' => 1,
            'entries' => [
                ['key' => 'total_users', 'order' => 0, 'colSpan' => 2, 'rowSpan' => 1, 'hidden' => false],
                'garbage',
                ['key' => 'no order'],
                ['key' => 'active_users', 'order' => 1, 'unexpected' => true],
                ['key' => 'new_users', 'order' => 2],
            ],
            'instances' => [
                ['key' => 'a#1', 'catalogue' => 'a', 'binding' => []],
                ['catalogue' => 'a', 'binding' => []],
                'garbage',
            ],
        ]);

        $this->assertSame(['total_users', 'new_users'], array_column($out['entries'], 'key'));
        $this->assertSame(['a#1'], array_column($out['instances'], 'key'));
    }

    public function test_it_caps_at_the_declared_limits(): void
    {
        $entries = [];
        for ($i = 0; $i < LayoutShape::MAX_ENTRIES + 20; $i++) {
            $entries[] = ['key' => 'k'.$i, 'order' => 0];
        }

        $instances = [];
        for ($i = 0; $i < LayoutShape::MAX_INSTANCES + 20; $i++) {
            $instances[] = ['key' => 'i'.$i, 'catalogue' => 'a', 'binding' => []];
        }

        $out = $this->assertShape(['version' => 1, 'entries' => $entries, 'instances' => $instances]);

        $this->assertCount(LayoutShape::MAX_ENTRIES, $out['entries']);
        $this->assertCount(LayoutShape::MAX_INSTANCES, $out['instances']);
    }

    public function test_assert_is_unchanged_and_still_rejects_on_the_write_path(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        LayoutShape::assert(['version' => 1, 'entries' => ['garbage'], 'instances' => []], 'payload');
    }
}
