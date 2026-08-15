<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A row-cap refusal must be a clean, machine-readable payload. Letting it fall
 * through to the exception handler returns a full HTML debug page carrying
 * request/user context — a leak, and unparseable to the XHR caller that asked.
 */
class RefusalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function csv(int $rows): UploadedFile
    {
        $lines = ['Full Name,E-Mail'];

        for ($i = 1; $i <= $rows; $i++) {
            $lines[] = "Person {$i},person{$i}@example.com";
        }

        return UploadedFile::fake()->createWithContent('import.csv', implode("\n", $lines) . "\n");
    }

    // --- import --------------------------------------------------------------

    public function test_import_row_cap_answers_json_to_an_xhr_caller(): void
    {
        config(['myra.imports.max_rows' => 1]);
        $this->actingAsRole('manager');

        $response = $this->post(
            route('admin.import.preview', ['resource' => 'users']),
            ['file' => $this->csv(30)],
            ['Accept' => 'application/json'],
        );

        $response->assertStatus(422);
        $response->assertJsonPath('max', 1);
        $this->assertIsString($response->json('message'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_import_row_cap_answers_plain_text_to_a_document_caller(): void
    {
        config(['myra.imports.max_rows' => 1]);
        $this->actingAsRole('manager');

        $response = $this->post(
            route('admin.import.preview', ['resource' => 'users']),
            ['file' => $this->csv(30)],
            ['Accept' => 'text/html'],
        );

        $response->assertStatus(422);
        $this->assertSame('text/plain; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_the_validate_endpoint_row_cap_is_also_a_clean_payload(): void
    {
        $this->actingAsRole('manager');

        $preview = $this->post(
            route('admin.import.preview', ['resource' => 'users']),
            ['file' => $this->csv(5)],
            ['Accept' => 'application/json'],
        )->assertOk()->json();

        // Tighten the cap only after staging, so validate() is the endpoint that refuses.
        config(['myra.imports.max_rows' => 1]);

        $this->postJson(route('admin.import.validate', ['resource' => 'users']), [
            'token' => $preview['token'],
            'mapping' => ['name' => 'Full Name', 'email' => 'E-Mail'],
        ])
            ->assertStatus(422)
            ->assertJsonPath('max', 1)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    // --- export --------------------------------------------------------------

    public function test_export_row_cap_answers_json_to_an_xhr_caller(): void
    {
        config(['myra.exports.max_rows' => 1]);

        $manager = $this->actingAsRole('manager');
        User::factory()->count(3)->create(['created_by' => $manager->id]);

        $response = $this->getJson(route('admin.users.export-csv'));

        $response->assertStatus(422);
        $response->assertJsonPath('max', 1);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('<html', $response->getContent());
    }

    public function test_export_row_cap_answers_plain_text_to_a_document_caller(): void
    {
        config(['myra.exports.max_rows' => 1]);

        $manager = $this->actingAsRole('manager');
        User::factory()->count(3)->create(['created_by' => $manager->id]);

        $response = $this->get(route('admin.users.export-csv'));

        $response->assertStatus(422);
        $this->assertSame('text/plain; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('<html', $response->getContent());
    }
}
