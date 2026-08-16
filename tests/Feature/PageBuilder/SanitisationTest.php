<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\SectionRegistry;
use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Tests\TestCase;

/**
 * MYRA v2.7 [D] — authored content is untrusted at render.
 *
 * Blocks are written in the admin and served to anonymous visitors, so every
 * assertion here goes through the REAL write endpoint and then reads the REAL
 * public payload. A sanitiser that only runs in a unit test is not a defence.
 */
class SanitisationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
        SectionRegistry::flush();
        SectionRegistry::seed();
    }

    /** @return array<string,mixed> */
    private function defaultsFor(string $type): array
    {
        foreach (SectionRegistry::toClientSchema() as $schema) {
            if ($schema['key'] !== $type) {
                continue;
            }

            $data = [];
            foreach ($schema['fields'] as $field) {
                $data[$field['name']] = $field['default'];
            }

            return $data;
        }

        $this->fail("Section type [{$type}] is not registered.");
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function newRow(string $type, array $data = []): array
    {
        return [
            'type' => $type,
            'enabled' => true,
            'variant' => [],
            'data' => array_replace($this->defaultsFor($type), $data),
        ];
    }

    /** @param array<int,array<string,mixed>> $blocks */
    private function saveBlocks(array $blocks): \Illuminate\Testing\TestResponse
    {
        return $this->put(route('admin.landing.builder.update'), ['blocks' => $blocks]);
    }

    /** @return array<int,array<string,mixed>> */
    private function storedBlocks(): array
    {
        return app(HomepageSettings::class)->blocks ?? [];
    }

    /** @return array<int,array<string,mixed>> */
    private function publishedBlocks(): array
    {
        return $this->get('/')->assertOk()->viewData('page')['props']['blocks'];
    }

    public function test_script_tags_in_a_rich_text_block_never_reach_the_public_page(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([
            $this->newRow('rich_text', [
                'heading' => 'Notes',
                'body' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(1)>',
            ]),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $stored = $this->storedBlocks()[0]['data']['body'];
        $published = $this->publishedBlocks()[0]['data']['body'];

        foreach (['stored' => $stored, 'published' => $published] as $where => $html) {
            $this->assertStringContainsString('<p>ok</p>', $html, "The paragraph did not survive in the {$where} value.");
            $this->assertStringNotContainsString('<script', $html, "A script tag reached the {$where} value.");
            $this->assertStringNotContainsString('onerror', $html, "An event handler reached the {$where} value.");
            $this->assertStringNotContainsString('alert(1)', $html);
        }
    }

    public function test_an_iframe_in_rich_text_is_removed_entirely(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([
            $this->newRow('rich_text', [
                'heading' => 'Embed',
                'body' => '<p>before</p><iframe src="https://evil.example/x"><b>swallowed</b></iframe><p>after</p>',
            ]),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $html = $this->publishedBlocks()[0]['data']['body'];

        $this->assertStringContainsString('before', $html);
        $this->assertStringContainsString('after', $html);
        $this->assertStringNotContainsString('iframe', $html);
        $this->assertStringNotContainsString(
            'swallowed',
            $html,
            'A forbidden tag loses its whole subtree, it is not merely unwrapped.',
        );
        $this->assertStringNotContainsString('evil.example', $html);
    }

    public function test_a_javascript_url_in_any_url_field_is_neutralised(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([
            $this->newRow('hero', ['title' => 'Hero', 'cta_url' => 'javascript:alert(1)']),
            $this->newRow('cta', ['title' => 'Cta', 'button_url' => ' JaVaScRiPt:alert(1)']),
            $this->newRow('image', ['alt' => 'Required alt text', 'link_url' => 'data:text/html,<script>']),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $published = $this->publishedBlocks();

        $this->assertSame('', $published[0]['data']['cta_url']);
        $this->assertSame('', $published[1]['data']['button_url']);
        $this->assertSame('', $published[2]['data']['link_url']);

        $stored = $this->storedBlocks();

        $this->assertSame('', $stored[0]['data']['cta_url'], 'The unsafe value must not survive in storage either.');
        $this->assertSame('', $stored[1]['data']['button_url']);
        $this->assertSame('', $stored[2]['data']['link_url']);
    }

    public function test_a_relative_and_an_anchor_and_a_mailto_url_all_survive(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([
            $this->newRow('hero', ['title' => 'Relative', 'cta_url' => '/register?plan=team']),
            $this->newRow('cta', ['title' => 'Anchor', 'button_url' => '#pricing']),
            $this->newRow('image', ['alt' => 'Contact card', 'link_url' => 'mailto:hello@example.com']),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $published = $this->publishedBlocks();

        $this->assertSame('/register?plan=team', $published[0]['data']['cta_url']);
        $this->assertSame('#pricing', $published[1]['data']['button_url']);
        $this->assertSame('mailto:hello@example.com', $published[2]['data']['link_url']);
    }

    public function test_undeclared_data_keys_are_dropped_on_save(): void
    {
        $this->actingAsSuperAdmin();

        $row = $this->newRow('hero', ['title' => 'Only declared keys']);
        $row['data']['enabled'] = false;
        $row['data']['id'] = 'attempted-envelope-overwrite';
        $row['data']['smuggled'] = '<script>alert(1)</script>';

        $this->saveBlocks([$row])->assertSessionHasNoErrors()->assertRedirect();

        $stored = $this->storedBlocks()[0];

        $this->assertArrayNotHasKey('smuggled', $stored['data']);
        $this->assertArrayNotHasKey('id', $stored['data'], 'The envelope keys must not be shadowable from data.');
        $this->assertArrayNotHasKey('enabled', $stored['data']);
        $this->assertTrue($stored['enabled']);
        $this->assertNotSame('attempted-envelope-overwrite', $stored['id']);

        $published = $this->publishedBlocks()[0];

        $this->assertSame('Only declared keys', $published['data']['title']);
        $this->assertArrayNotHasKey('smuggled', $published['data']);
    }

    public function test_the_write_endpoint_is_closed_to_a_user_without_settings_edit(): void
    {
        $this->actingAsUser();

        $before = $this->storedBlocks();

        $this->saveBlocks([$this->newRow('hero', ['title' => 'Nope'])])->assertForbidden();

        $this->assertSame($before, $this->storedBlocks(), 'A refused write must not touch storage.');
    }
}
