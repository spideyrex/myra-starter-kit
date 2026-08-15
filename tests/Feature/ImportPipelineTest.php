<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportPipelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function csv(array $rows): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    private function validCsv(int $rows = 3): UploadedFile
    {
        $out = [['Full Name', 'E-Mail', 'Mobile', 'State']];

        for ($i = 1; $i <= $rows; $i++) {
            $out[] = ["Person {$i}", "person{$i}@example.com", "0100000{$i}", 'active'];
        }

        return $this->csv($out);
    }

    private function preview(UploadedFile $file, string $resource = 'users'): array
    {
        return $this->post(route('admin.import.preview', ['resource' => $resource]), ['file' => $file])
            ->assertOk()
            ->json();
    }

    private function mapping(): array
    {
        return [
            'name' => 'Full Name',
            'email' => 'E-Mail',
            'password' => '',
            'phone' => 'Mobile',
            'status' => 'State',
            'role' => '',
        ];
    }

    // --- mapping whitelist ---------------------------------------------------

    public function test_auto_mapping_matches_aliases_and_normalised_headers(): void
    {
        $this->actingAsRole('manager');
        $preview = $this->preview($this->validCsv());

        $this->assertSame('Full Name', $preview['mapping']['name']);
        $this->assertSame('E-Mail', $preview['mapping']['email']);
        $this->assertSame('Mobile', $preview['mapping']['phone']);
        $this->assertSame('State', $preview['mapping']['status']);
    }

    public function test_a_mapping_key_outside_the_declared_columns_is_rejected(): void
    {
        $this->actingAsRole('manager');
        $preview = $this->preview($this->validCsv());

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => $this->mapping() + ['created_by' => 'Full Name'],
        ])->assertStatus(422);

        $this->assertNull(User::where('email', 'person1@example.com')->first());
    }

    public function test_a_mapping_value_that_is_not_a_staged_header_is_rejected(): void
    {
        $this->actingAsRole('manager');
        $preview = $this->preview($this->validCsv());

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => ['name' => 'Full Name', 'email' => 'password_column'],
        ])->assertStatus(422);
    }

    public function test_a_missing_required_mapping_is_rejected(): void
    {
        $this->actingAsRole('manager');
        $preview = $this->preview($this->validCsv());

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => ['name' => 'Full Name', 'email' => ''],
        ])->assertStatus(422);
    }

    // --- token / TOCTOU ------------------------------------------------------

    public function test_another_users_token_is_refused(): void
    {
        $this->actingAsRole('manager');
        $preview = $this->preview($this->validCsv());

        $this->actingAsRole('manager');

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => $this->mapping(),
        ])->assertStatus(403);
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->actingAsRole('manager');
        $preview = $this->preview($this->validCsv());

        Cache::flush();

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => $this->mapping(),
        ])->assertStatus(422);
    }

    public function test_a_malformed_token_is_rejected_before_any_cache_lookup(): void
    {
        $this->actingAsRole('manager');

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => '../../etc/passwd',
            'offset' => 0,
            'mapping' => $this->mapping(),
        ])->assertStatus(422);
    }

    // --- validate ------------------------------------------------------------

    public function test_validate_reports_per_cell_errors_and_writes_nothing(): void
    {
        $this->actingAsRole('manager');

        $preview = $this->preview($this->csv([
            ['Full Name', 'E-Mail', 'Mobile', 'State'],
            ['Ada', 'ada@example.com', '0100', 'active'],
            ['Alan', 'not-an-email', '0101', 'sideways'],
        ]));

        $before = User::count();

        $body = $this->postJson(route('admin.import.validate', ['resource' => 'users']), [
            'token' => $preview['token'],
            'mapping' => $this->mapping(),
        ])->assertOk()->json();

        $this->assertSame(2, $body['totalRows']);
        $this->assertSame(1, $body['invalidRows']);
        $this->assertArrayHasKey('email', $body['rows'][1]['errors']);
        $this->assertArrayHasKey('status', $body['rows'][1]['errors']);
        $this->assertSame($before, User::count());
    }

    // --- commit --------------------------------------------------------------

    public function test_a_file_completes_across_three_resumable_chunks(): void
    {
        config(['myra.imports.chunk_size' => 2]);

        $this->actingAsRole('manager');
        $preview = $this->preview($this->validCsv(5));

        $offset = 0;
        $line = 1;
        $offsets = [];
        $imported = 0;
        $calls = 0;

        do {
            $body = $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
                'token' => $preview['token'],
                'offset' => $offset,
                'line' => $line,
                'mapping' => $this->mapping(),
            ])->assertOk()->json();

            $offsets[] = $body['next_offset'];
            $imported += $body['imported'];
            $offset = $body['next_offset'];
            $line = $body['next_line'];
            $calls++;
        } while (! $body['done'] && $calls < 10);

        $this->assertTrue($body['done']);
        $this->assertSame('done', $body['status']);
        $this->assertSame(3, $calls);
        $this->assertSame(5, $imported);
        $this->assertSame($offsets, array_values(array_unique($offsets)));
        $this->assertGreaterThan($offsets[0], $offsets[1]);
        $this->assertSame(5, User::where('email', 'like', 'person%@example.com')->count());
    }

    public function test_an_invalid_row_does_not_prevent_the_valid_rows_in_its_chunk(): void
    {
        $this->actingAsRole('manager');

        $preview = $this->preview($this->csv([
            ['Full Name', 'E-Mail', 'Mobile', 'State'],
            ['Ada', 'ada@example.com', '0100', 'active'],
            ['Broken', 'not-an-email', '0101', 'active'],
            ['Grace', 'grace@example.com', '0102', 'active'],
        ]));

        $body = $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => $this->mapping(),
        ])->assertOk()->json();

        $this->assertSame(2, $body['imported']);
        $this->assertSame(1, $body['failed']);
        $this->assertTrue($body['done']);
        $this->assertNotNull($body['failuresUrl']);

        $this->assertNotNull(User::where('email', 'ada@example.com')->first());
        $this->assertNotNull(User::where('email', 'grace@example.com')->first());
        $this->assertNull(User::where('name', 'Broken')->first());
    }

    // --- privilege escalation ------------------------------------------------

    public function test_a_non_super_admin_cannot_grant_a_privileged_role(): void
    {
        $this->actingAsRole('manager');

        $preview = $this->preview($this->csv([
            ['Full Name', 'E-Mail', 'Group'],
            ['Sneaky', 'sneaky@example.com', 'super-admin'],
        ]));

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => ['name' => 'Full Name', 'email' => 'E-Mail', 'role' => 'Group'],
        ])->assertOk();

        $created = User::where('email', 'sneaky@example.com')->firstOrFail();

        $this->assertFalse($created->hasRole('super-admin'));
    }

    public function test_a_super_admin_can_grant_a_privileged_role(): void
    {
        $this->actingAsSuperAdmin();

        $preview = $this->preview($this->csv([
            ['Full Name', 'E-Mail', 'Group'],
            ['Legit', 'legit@example.com', 'admin'],
        ]));

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => ['name' => 'Full Name', 'email' => 'E-Mail', 'role' => 'Group'],
        ])->assertOk();

        $this->assertTrue(User::where('email', 'legit@example.com')->firstOrFail()->hasRole('admin'));
    }

    // --- failures report -----------------------------------------------------

    public function test_sensitive_columns_are_masked_in_the_failures_csv(): void
    {
        $this->actingAsRole('manager');

        $preview = $this->preview($this->csv([
            ['Full Name', 'E-Mail', 'Pass'],
            ['Broken', 'not-an-email', 'hunter2hunter2'],
        ]));

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => ['name' => 'Full Name', 'email' => 'E-Mail', 'password' => 'Pass'],
        ])->assertOk();

        $body = $this->get(route('admin.import.failures', [
            'resource' => 'users',
            'token' => $preview['token'],
        ]))->assertOk()->streamedContent();

        $this->assertStringNotContainsString('hunter2hunter2', $body);
        $this->assertStringContainsString('***', $body);
    }

    public function test_failures_download_is_refused_for_another_user(): void
    {
        $this->actingAsRole('manager');

        $preview = $this->preview($this->csv([
            ['Full Name', 'E-Mail'],
            ['Broken', 'not-an-email'],
        ]));

        $this->postJson(route('admin.import.commit', ['resource' => 'users']), [
            'token' => $preview['token'],
            'offset' => 0,
            'mapping' => ['name' => 'Full Name', 'email' => 'E-Mail'],
        ])->assertOk();

        $this->actingAsRole('manager');

        $this->get(route('admin.import.failures', [
            'resource' => 'users',
            'token' => $preview['token'],
        ]))->assertStatus(403);
    }

    // --- guards --------------------------------------------------------------

    public function test_the_permission_comes_from_the_registry_not_the_route(): void
    {
        $this->actingAsRole('editor');

        $this->post(route('admin.import.preview', ['resource' => 'users']), [
            'file' => $this->validCsv(),
        ])->assertStatus(403);
    }

    public function test_an_unregistered_resource_is_a_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.import.preview', ['resource' => 'nope']), [
            'file' => $this->validCsv(),
        ])->assertNotFound();
    }

    public function test_too_many_columns_is_refused(): void
    {
        config(['myra.imports.max_columns' => 2]);
        $this->actingAsRole('manager');

        $this->post(route('admin.import.preview', ['resource' => 'users']), [
            'file' => $this->validCsv(),
        ])->assertStatus(422);
    }

    public function test_too_many_rows_is_refused_before_any_write(): void
    {
        config(['myra.imports.max_rows' => 1]);
        $this->actingAsRole('manager');
        $before = User::count();

        $this->post(route('admin.import.preview', ['resource' => 'users']), [
            'file' => $this->validCsv(20),
        ])->assertStatus(422);

        $this->assertSame($before, User::count());
    }

    public function test_write_endpoints_are_rate_limited(): void
    {
        $this->actingAsRole('manager');

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('admin.import.preview', ['resource' => 'users']), [
                'file' => $this->validCsv(1),
            ]);
        }

        $this->post(route('admin.import.preview', ['resource' => 'users']), [
            'file' => $this->validCsv(1),
        ])->assertStatus(429);
    }
}
