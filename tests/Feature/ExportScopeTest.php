<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Csv;
use Tests\TestCase;

/**
 * Regression cover for the export data leak: UserController::exportCsv used to
 * build its own query, bypassing UserService's created_by ownership scope and
 * super-admin exclusion. Any holder of users.view could export every user.
 */
class ExportScopeTest extends TestCase
{
    private function csvRows(string $body): array
    {
        $lines = array_values(array_filter(explode("\n", trim(str_replace("\r\n", "\n", $body)))));

        return array_map('str_getcsv', $lines);
    }

    private function seedOwnedAndForeignUsers(User $owner): void
    {
        $stranger = User::factory()->create();

        User::factory()->count(2)->create(['created_by' => $owner->id]);
        User::factory()->count(7)->create(['created_by' => $stranger->id]);
    }

    public function test_export_is_scoped_to_records_the_actor_owns(): void
    {
        $manager = $this->actingAsRole('manager');
        $this->seedOwnedAndForeignUsers($manager);

        $response = $this->get(route('admin.users.export-csv'));
        $response->assertOk();

        $rows = $this->csvRows($response->streamedContent());

        // 1 header + exactly the 2 users this manager created.
        $this->assertCount(3, $rows);
    }

    public function test_super_admin_export_is_not_scoped(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $this->seedOwnedAndForeignUsers($admin);

        $rows = $this->csvRows($this->get(route('admin.users.export-csv'))->streamedContent());

        $this->assertCount(User::count() + 1, $rows);
    }

    public function test_exported_row_count_matches_the_paginated_total(): void
    {
        $manager = $this->actingAsRole('manager');
        $this->seedOwnedAndForeignUsers($manager);

        $paginated = app(\App\Services\UserService::class)->list(request());
        $rows = $this->csvRows($this->get(route('admin.users.export-csv'))->streamedContent());

        $this->assertSame($paginated->total(), count($rows) - 1);
    }

    public function test_status_filter_narrows_the_export(): void
    {
        $manager = $this->actingAsRole('manager');
        User::factory()->create(['created_by' => $manager->id, 'status' => 'suspended']);
        User::factory()->count(3)->create(['created_by' => $manager->id, 'status' => 'active']);

        $rows = $this->csvRows(
            $this->get(route('admin.users.export-csv', ['status' => 'suspended']))->streamedContent(),
        );

        $this->assertCount(2, $rows);
    }

    public function test_search_filter_narrows_the_export(): void
    {
        $manager = $this->actingAsRole('manager');
        User::factory()->create(['created_by' => $manager->id, 'name' => 'Zzyzx Findme']);
        User::factory()->count(3)->create(['created_by' => $manager->id]);

        $rows = $this->csvRows(
            $this->get(route('admin.users.export-csv', ['search' => 'Zzyzx']))->streamedContent(),
        );

        $this->assertCount(2, $rows);
        $this->assertStringContainsString('Zzyzx Findme', $rows[1][1]);
    }

    public function test_over_the_row_cap_it_refuses_and_emits_no_partial_file(): void
    {
        config(['myra.exports.max_rows' => 1]);

        $manager = $this->actingAsRole('manager');
        User::factory()->count(3)->create(['created_by' => $manager->id]);

        $response = $this->get(route('admin.users.export-csv'));

        $response->assertStatus(422);
        $this->assertStringNotContainsString('@example', $response->getContent());
    }

    public function test_the_row_cap_refusal_is_a_clean_payload_not_a_rendered_error_page(): void
    {
        config(['myra.exports.max_rows' => 1]);

        $manager = $this->actingAsRole('manager');
        User::factory()->count(3)->create(['created_by' => $manager->id]);

        $this->getJson(route('admin.users.export-csv'))
            ->assertStatus(422)
            ->assertJsonPath('max', 1);

        $html = $this->get(route('admin.users.export-csv'));
        $html->assertStatus(422);
        $this->assertStringNotContainsString('<html', $html->getContent());
        $this->assertStringNotContainsString('text/csv', (string) $html->headers->get('Content-Type'));
    }

    public function test_an_undeclared_column_key_is_dropped(): void
    {
        $manager = $this->actingAsRole('manager');
        User::factory()->create(['created_by' => $manager->id]);

        $rows = $this->csvRows(
            $this->get(route('admin.users.export-csv', ['columns' => ['name', 'password', 'remember_token']]))
                ->streamedContent(),
        );

        $this->assertSame(['Name'], $rows[0]);
    }

    public function test_formula_injection_is_neutralised_in_the_csv(): void
    {
        $manager = $this->actingAsRole('manager');
        User::factory()->create(['created_by' => $manager->id, 'name' => '=cmd|/c calc']);

        $rows = $this->csvRows(
            $this->get(route('admin.users.export-csv', ['columns' => ['name']]))->streamedContent(),
        );

        $this->assertSame("'=cmd|/c calc", $rows[1][0]);
    }

    public function test_filename_is_sanitised_and_cannot_inject_a_header(): void
    {
        $this->assertStringNotContainsString('/', Csv::filename('../../etc/passwd'));
        $this->assertStringNotContainsString('..', Csv::filename('../../etc/passwd'));
        $this->assertStringStartsWith('etc-passwd-', Csv::filename('../../etc/passwd'));
        $this->assertStringEndsWith('.csv', Csv::filename('../../etc/passwd'));

        $injected = Csv::filename("users\r\nSet-Cookie: a=b");
        $this->assertStringNotContainsString("\r", $injected);
        $this->assertStringNotContainsString("\n", $injected);

        $this->assertStringEndsWith('.xlsx', Csv::filename('users', 'xlsx'));
        $this->assertLessThanOrEqual(100, strlen(Csv::filename(str_repeat('a', 300))));
    }

    public function test_export_response_carries_hardening_headers(): void
    {
        $this->actingAsRole('manager');

        $response = $this->get(route('admin.users.export-csv'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }

    public function test_export_requires_the_view_permission(): void
    {
        $this->actingAsRole('editor');

        $this->get(route('admin.users.export-csv'))->assertForbidden();
    }
}
