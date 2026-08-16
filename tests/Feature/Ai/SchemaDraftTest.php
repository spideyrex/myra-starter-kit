<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\AiService;
use Tests\Support\FakeAiService;
use Tests\TestCase;

/**
 * A schema draft is text a developer reads. Nothing is written, nothing is
 * migrated, nothing is eval'd — and anything outside the closed type list or
 * the identifier regex is dropped rather than coerced.
 */
class SchemaDraftTest extends TestCase
{
    private function enable(string $reply): FakeAiService
    {
        config()->set('myra.ai.features.schema', true);

        $fake = new FakeAiService([$reply], true);
        $this->app->instance(AiService::class, $fake);

        return $fake;
    }

    private function postDraft(string $prompt = 'an invoice with a number, a total and a due date'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('admin.ai.schema'), ['prompt' => $prompt]);
    }

    public function test_a_clean_draft_is_returned_as_a_command(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable('{"model":"Invoice","fields":[{"name":"number","type":"text"},{"name":"total","type":"number"},{"name":"due_at","type":"date"}]}');

        $response = $this->postDraft()->assertOk();

        $this->assertSame('Invoice', $response->json('draft.model'));
        $this->assertCount(3, $response->json('draft.fields'));
        $this->assertStringContainsString('make:myra-resource Invoice', $response->json('draft.command'));
    }

    public function test_fields_outside_the_allow_list_are_dropped_not_coerced(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable('{"model":"Invoice","fields":['
            .'{"name":"number","type":"text"},'
            .'{"name":"total; DROP TABLE users","type":"text"},'
            .'{"name":"weird","type":"json"},'
            .'{"name":"1bad","type":"text"}'
            .']}');

        $fields = $this->postDraft()->assertOk()->json('draft.fields');

        $this->assertSame([['name' => 'number', 'type' => 'text']], $fields);
    }

    public function test_a_junk_model_name_is_a_422(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable('{"model":"invoices; --","fields":[{"name":"number","type":"text"}]}');

        $this->postDraft()->assertStatus(422);
    }

    public function test_it_404s_when_the_flag_is_off(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable('{"model":"Invoice","fields":[{"name":"number","type":"text"}]}');
        config()->set('myra.ai.features.schema', false);

        $this->postDraft()->assertNotFound();
    }

    public function test_it_403s_without_the_ability(): void
    {
        $this->actingAsUser();
        $this->enable('{"model":"Invoice","fields":[{"name":"number","type":"text"}]}');

        $this->postDraft()->assertForbidden();
    }
}
