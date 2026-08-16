<?php

namespace Tests\Feature\Generators;

use App\Console\Commands\Myra\MakeResourceCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * REAL GENERATOR OUTPUT. Every assertion below runs against what
 * `make:myra-resource` actually renders from stubs/admin/*.stub — never
 * against a copy of a stub. `--print` is a true dry run, so the suite can
 * exercise the generator without writing into the repository.
 */
class StubDesignPassTest extends TestCase
{
    /** @return array<string,string> destination => rendered content */
    private function generate(array $options = []): array
    {
        Artisan::call('make:myra-resource', array_merge([
            'name' => 'Widget',
            '--model' => true,
            '--print' => true,
        ], $options));

        return $this->sections(Artisan::output());
    }

    /** @return array<string,string> */
    private function sections(string $output): array
    {
        $output = str_replace("\r\n", "\n", $output);
        $parts = preg_split('/^===== (.+?) =====$/m', $output, -1, PREG_SPLIT_DELIM_CAPTURE);

        $sections = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $sections[trim($parts[$i])] = $parts[$i + 1] ?? '';
        }

        return $sections;
    }

    private function section(array $sections, string $needle): string
    {
        foreach ($sections as $name => $content) {
            if (str_contains($name, $needle)) {
                return $content;
            }
        }

        $this->fail("Generator produced no section matching [{$needle}]. Got: ".implode(', ', array_keys($sections)));
    }

    public function test_print_is_a_true_dry_run(): void
    {
        $this->generate();

        $this->assertFileDoesNotExist(app_path('Models/Widget.php'));
        $this->assertFileDoesNotExist(app_path('Http/Controllers/Admin/WidgetController.php'));
        $this->assertFileDoesNotExist(resource_path('js/Pages/Admin/Widgets/Index.vue'));
    }

    public function test_the_generated_model_is_ownership_scoped_and_never_fillable_by_owner(): void
    {
        $model = $this->section($this->generate(), 'app/Models/Widget.php');

        $this->assertStringContainsString('use OwnedByUser;', $model);
        $this->assertStringContainsString('use App\Models\Traits\OwnedByUser;', $model);

        $fillable = $this->fillableBlock($model);
        $this->assertStringNotContainsString("'created_by'", $fillable);
        $this->assertStringContainsString("'name'", $fillable);
    }

    public function test_unscoped_drops_ownership_entirely(): void
    {
        $model = $this->section($this->generate(['--unscoped' => true]), 'app/Models/Widget.php');

        $this->assertStringNotContainsString('OwnedByUser', $model);

        $migration = $this->section($this->generate(['--unscoped' => true]), 'create_widgets_table');
        $this->assertStringNotContainsString('created_by', $migration);
    }

    public function test_soft_deletes_are_opt_in(): void
    {
        $this->assertStringNotContainsString('SoftDeletes', $this->section($this->generate(), 'app/Models/Widget.php'));

        $sections = $this->generate(['--soft-deletes' => true]);
        $this->assertStringContainsString('use SoftDeletes;', $this->section($sections, 'app/Models/Widget.php'));
        $this->assertStringContainsString('softDeletes()', $this->section($sections, 'create_widgets_table'));
    }

    public function test_the_generated_migration_indexes_the_owner_column(): void
    {
        $migration = $this->section($this->generate(), 'create_widgets_table');

        $this->assertStringContainsString(
            "\$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();",
            $migration,
        );
        $this->assertStringContainsString("\$table->index(['created_by', 'created_at']);", $migration);
        $this->assertStringContainsString("Schema::create('widgets'", $migration);
        $this->assertStringContainsString("Schema::dropIfExists('widgets');", $migration);
    }

    public function test_every_controller_method_authorizes_defensively(): void
    {
        $controller = $this->section($this->generate(), 'WidgetController.php');

        foreach (['view', 'create', 'create', 'edit', 'edit', 'delete'] as $ability) {
            $this->assertStringContainsString("Gate::authorize('widgets.{$ability}')", $controller);
        }

        // Gate::authorize(), never $this->authorize().
        $this->assertStringNotContainsString('$this->authorize(', $controller);
        $this->assertSame(6, substr_count($controller, 'Gate::authorize('));
    }

    public function test_validation_moved_into_form_requests(): void
    {
        $sections = $this->generate();
        $controller = $this->section($sections, 'WidgetController.php');

        $this->assertStringContainsString('StoreWidgetRequest $request', $controller);
        $this->assertStringContainsString('UpdateWidgetRequest $request', $controller);
        $this->assertStringContainsString('use App\Http\Requests\Admin\StoreWidgetRequest;', $controller);
        $this->assertStringNotContainsString('$request->validate(', $controller);

        $store = $this->section($sections, 'StoreWidgetRequest.php');
        $this->assertStringContainsString("'name' => ['required', 'string', 'max:255']", $store);
        $this->assertStringContainsString('TODO', $store);
    }

    public function test_the_service_declares_its_own_sort_whitelist(): void
    {
        $service = $this->section($this->generate(), 'WidgetService.php');

        $this->assertStringContainsString("sortable: ['id', 'name', 'created_at']", $service);
        $this->assertStringContainsString('applySearchAndPaginate(', $service);
        // No hand-rolled LIKE: searching goes through the trait, which uses Sql::orWhereLike.
        $this->assertStringNotContainsString("'like'", $service);
        $this->assertStringNotContainsString('whereRaw', $service);
    }

    public function test_the_generated_index_table_enables_saved_views(): void
    {
        $index = $this->section($this->generate(), 'Widgets/Index.vue');

        $this->assertStringContainsString('table-key="widgets"', $index);
    }

    public function test_the_generated_vue_pages_contain_no_hardcoded_english(): void
    {
        $sections = $this->generate();

        foreach (['Widgets/Index.vue', 'Widgets/Create.vue', 'Widgets/Edit.vue'] as $page) {
            $vue = $this->section($sections, $page);

            $this->assertStringContainsString("useI18n", $vue);
            // Unbound title/description attributes are the hardcoded-copy smell.
            $this->assertDoesNotMatchRegularExpression('/\s(title|description|submit-text|search-placeholder)="[A-Za-z]/', $vue);
            $this->assertStringNotContainsString('Add Widget', $vue);
            $this->assertStringNotContainsString('Manage widgets.', $vue);
        }
    }

    public function test_every_translation_key_the_pages_reference_is_written_by_the_generator(): void
    {
        $sections = $this->generate();
        $known = array_keys(MakeResourceCommand::translationKeys('widgets', 'Widgets', 'Widget'));

        $referenced = [];
        foreach (['Widgets/Index.vue', 'Widgets/Create.vue', 'Widgets/Edit.vue'] as $page) {
            preg_match_all("/t\('(generated\.[^']+)'\)/", $this->section($sections, $page), $matches);
            $referenced = array_merge($referenced, $matches[1]);
        }

        $referenced = array_values(array_unique($referenced));

        $this->assertNotEmpty($referenced, 'The generated pages reference no generated.* keys at all.');
        $this->assertSame([], array_values(array_diff($referenced, $known)), 'A page references a key the generator never writes.');
    }

    public function test_the_generator_reports_the_i18n_keys_it_would_write(): void
    {
        $keys = $this->section($this->generate(), 'i18n keys');

        $this->assertStringContainsString('generated.widgets.title = Widgets', $keys);
        $this->assertStringContainsString('generated.widgets.columns.name = Name', $keys);
    }

    public function test_the_generated_feature_test_proves_scoping_and_the_sort_whitelist(): void
    {
        $test = $this->section($this->generate(), 'WidgetResourceTest.php');

        $this->assertStringContainsString('assertForbidden', $test);
        $this->assertStringContainsString("'sort' => 'password'", $test);
        $this->assertStringContainsString('test_a_non_owner_cannot_see_another_users_row', $test);
        // It must not depend on any optional testing helper from another bundle.
        $this->assertStringNotContainsString('InteractsWithMyra', $test);
    }

    public function test_the_generated_routes_are_permission_gated_per_ability(): void
    {
        $routes = $this->section($this->generate(), 'routes/web.php');

        $this->assertStringContainsString("permission:widgets.view", $routes);
        $this->assertStringContainsString("permission:widgets.create", $routes);
        $this->assertStringContainsString("permission:widgets.edit", $routes);
        $this->assertStringContainsString("permission:widgets.delete", $routes);
    }

    public function test_the_group_option_is_actually_read(): void
    {
        $nav = $this->section($this->generate(['--group' => 'Catalogue']), 'nav');

        $this->assertStringContainsString('group=Catalogue', $nav);
    }

    public function test_the_three_locale_files_hold_identical_key_sets(): void
    {
        $sets = [];

        foreach (['en', 'ms', 'zh'] as $locale) {
            $data = json_decode(file_get_contents(resource_path("js/i18n/locales/{$locale}.json")), true);
            $this->assertIsArray($data, "{$locale}.json is not valid JSON");
            $sets[$locale] = $this->flatten($data);
            sort($sets[$locale]);
        }

        $this->assertSame($sets['en'], $sets['ms']);
        $this->assertSame($sets['en'], $sets['zh']);
    }

    /** @return string[] */
    private function flatten(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $dot = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value) && $value !== []) {
                $out = array_merge($out, $this->flatten($value, $dot));
                continue;
            }
            $out[] = $dot;
        }

        return $out;
    }

    private function fillableBlock(string $model): string
    {
        preg_match('/\$fillable = \[(.*?)\];/s', $model, $matches);

        $this->assertNotEmpty($matches, 'The generated model declares no $fillable.');

        return $matches[1];
    }
}
