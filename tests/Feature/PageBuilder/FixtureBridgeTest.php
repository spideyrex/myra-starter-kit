<?php

namespace Tests\Feature\PageBuilder;

use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/**
 * The bridge between the PHP suite and vitest is only worth anything if it
 * actually fails. It runs on one runner and the spec that consumes its output
 * runs on another, so nothing downstream would notice a bridge that quietly
 * rewrote its own expectations.
 */
class FixtureBridgeTest extends TestCase
{
    use SyncsPageBuilderFixtures;

    private const PATH = 'storage/app/fixture-bridge-test.json';

    protected function tearDown(): void
    {
        putenv('MYRA_WRITE_FIXTURES');
        @unlink(base_path(self::PATH));

        parent::tearDown();
    }

    /** @param array<string,mixed> $payload */
    private function write(array $payload): void
    {
        putenv('MYRA_WRITE_FIXTURES=1');
        $this->syncFixture(self::PATH, $payload);
        putenv('MYRA_WRITE_FIXTURES');
    }

    /** @param array<string,mixed> $payload */
    private function drifts(array $payload): bool
    {
        try {
            $this->syncFixture(self::PATH, $payload);
        } catch (AssertionFailedError) {
            return true;
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'template' => 'classic',
            'sectionOrder' => ['hero', 'cta'],
            'blocks' => [
                ['id' => 'row-0', 'type' => 'hero', 'variant' => [], 'data' => ['title' => 'Ship faster']],
                ['id' => 'row-1', 'type' => 'cta', 'variant' => [], 'data' => ['title' => 'Ready']],
            ],
        ];
    }

    public function test_the_written_file_is_what_the_next_run_asserts_against(): void
    {
        $this->write($this->payload());

        $this->assertFalse($this->drifts($this->payload()));
    }

    public function test_a_key_the_server_adds_later_does_not_fail_the_committed_sample(): void
    {
        $this->write($this->payload());

        $grown = $this->payload();
        $grown['blocks'][0]['data']['image_url'] = null;
        $grown['blocks'][0]['enabled'] = true;
        $grown['settings'] = ['hero_title' => 'Legacy'];

        $this->assertFalse($this->drifts($grown), 'An additive payload must not fail the fixture.');
    }

    public function test_a_changed_value_fails(): void
    {
        $this->write($this->payload());

        $changed = $this->payload();
        $changed['blocks'][0]['data']['title'] = 'Ship slower';

        $this->assertTrue($this->drifts($changed));
    }

    public function test_a_vanished_key_fails(): void
    {
        $this->write($this->payload());

        $thinned = $this->payload();
        unset($thinned['blocks'][1]['data']['title']);

        $this->assertTrue($this->drifts($thinned));
    }

    public function test_a_dropped_or_reordered_row_fails(): void
    {
        $this->write($this->payload());

        $dropped = $this->payload();
        array_pop($dropped['blocks']);

        $this->assertTrue($this->drifts($dropped));

        $reordered = $this->payload();
        $reordered['blocks'] = [$reordered['blocks'][1], $reordered['blocks'][0]];
        $reordered['blocks'][0]['id'] = 'row-0';
        $reordered['blocks'][1]['id'] = 'row-1';

        $this->assertTrue($this->drifts($reordered), 'The saved order is part of the payload.');
    }

    public function test_a_missing_file_fails_rather_than_being_created(): void
    {
        @unlink(base_path(self::PATH));

        $this->assertTrue($this->drifts($this->payload()));
        $this->assertFileDoesNotExist(base_path(self::PATH));
    }
}
