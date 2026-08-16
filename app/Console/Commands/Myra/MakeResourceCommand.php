<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeResourceCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-resource {name : Model name in PascalCase (e.g. Product)}
        {--model : Also generate the Eloquent model + migration}
        {--icon= : lucide-vue-next icon for the nav item (default LayoutGrid)}
        {--group= : Sidebar group label (default config myra.nav_group)}
        {--unscoped : Omit OwnedByUser + created_by (lookup tables)}
        {--soft-deletes : Add SoftDeletes + deleted_at}
        {--print : Print every rendered file and snippet; write nothing}';

    protected $description = 'Scaffold a full admin CRUD resource (model, migration, form requests, controller, service, 3 Vue pages, feature test) and auto-wire routes, nav, i18n and permissions';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $repl = $this->replacements($name);
        $plural = $repl['{{ modelPlural }}'];
        $prefix = $repl['{{ routePrefix }}'];
        $var = $repl['{{ modelVariable }}'];
        $print = (bool) $this->option('print');
        $icon = $this->option('icon') ?: config('myra.nav_icon', 'LayoutGrid');
        $group = $this->option('group') ?: config('myra.nav_group', 'Custom');
        $scoped = ! $this->option('unscoped');
        $softDeletes = (bool) $this->option('soft-deletes');

        $table = Str::snake($plural);
        $repl += $this->modelReplacements($scoped, $softDeletes, $table);

        /** @var array<string,string> destination => rendered content */
        $plan = [];

        if ($this->option('model')) {
            $plan["app/Models/{$name}.php"] = $this->renderStub('stubs/admin/model.stub', $repl) ?? '';
            $plan['database/migrations/'.date('Y_m_d_His')."_create_{$table}_table.php"]
                = $this->renderStub('stubs/admin/migration.create.stub', $repl) ?? '';
        }

        $plan["app/Http/Requests/Admin/Store{$name}Request.php"] = $this->renderStub('stubs/admin/request.store.stub', $repl) ?? '';
        $plan["app/Http/Requests/Admin/Update{$name}Request.php"] = $this->renderStub('stubs/admin/request.update.stub', $repl) ?? '';
        $plan["app/Http/Controllers/Admin/{$name}Controller.php"] = $this->renderStub('stubs/admin/controller.resource.stub', $repl) ?? '';
        $plan["app/Services/{$name}Service.php"] = $this->renderStub('stubs/admin/service.stub', $repl) ?? '';
        $plan["resources/js/Pages/Admin/{$plural}/Index.vue"] = $this->renderStub('stubs/admin/page.index.stub', $repl) ?? '';
        $plan["resources/js/Pages/Admin/{$plural}/Create.vue"] = $this->renderStub('stubs/admin/page.create.stub', $repl) ?? '';
        $plan["resources/js/Pages/Admin/{$plural}/Edit.vue"] = $this->renderStub('stubs/admin/page.edit.stub', $repl) ?? '';
        $plan["tests/Feature/Modules/{$name}ResourceTest.php"] = $this->renderStub('stubs/admin/test.feature.stub', $repl) ?? '';

        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}Controller";
        $snippet = implode("\n", [
            "    // {$plural}",
            "    Route::get('/{$prefix}', [{$fqcn}::class, 'index'])->middleware('permission:{$prefix}.view')->name('{$prefix}.index');",
            "    Route::get('/{$prefix}/create', [{$fqcn}::class, 'create'])->middleware('permission:{$prefix}.create')->name('{$prefix}.create');",
            "    Route::post('/{$prefix}', [{$fqcn}::class, 'store'])->middleware('permission:{$prefix}.create')->name('{$prefix}.store');",
            "    Route::get('/{$prefix}/{{$var}}/edit', [{$fqcn}::class, 'edit'])->middleware('permission:{$prefix}.edit')->name('{$prefix}.edit');",
            "    Route::put('/{$prefix}/{{$var}}', [{$fqcn}::class, 'update'])->middleware('permission:{$prefix}.edit')->name('{$prefix}.update');",
            "    Route::delete('/{$prefix}/{{$var}}', [{$fqcn}::class, 'destroy'])->middleware('permission:{$prefix}.delete')->name('{$prefix}.destroy');",
        ]);

        $translations = self::translationKeys($prefix, $plural, $name);

        if ($print) {
            foreach ($plan as $dest => $content) {
                $this->raw("===== {$dest} =====");
                $this->raw($content);
            }
            $this->raw('===== routes/web.php =====');
            $this->raw($snippet);
            $this->raw('===== i18n keys =====');
            foreach ($translations as $key => $value) {
                $this->raw("{$key} = {$value}");
            }
            $this->raw('===== nav =====');
            $this->raw("group={$group} icon={$icon} permission={$prefix}.view");

            return self::SUCCESS;
        }

        foreach ($plan as $dest => $content) {
            $this->writeRaw($dest, $content);
        }

        $this->addTranslations($translations);
        $this->syncPermissions($prefix, ['view', 'create', 'edit', 'delete']);
        $this->registerRoute($snippet, "{$prefix}.index", false);
        $this->registerNav($plural, "admin.{$prefix}.index", $icon, "{$prefix}.view", false, $group);

        $this->newLine();
        if (! $this->option('model')) {
            $this->components->warn('No model generated. Re-run with --model, or create it yourself using stubs/admin/model.stub.');
        }
        $this->components->info("Resource '{$name}' scaffolded → ".\App\Support\Myra::adminPath($prefix)." under the '{$group}' nav group (run `npm run build`).");

        return self::SUCCESS;
    }

    /** Tokens that differ between the scoped, unscoped and soft-deleting variants. */
    private function modelReplacements(bool $scoped, bool $softDeletes, string $table): array
    {
        $imports = [];
        $traits = [];
        $notes = '';
        $ownerColumn = '';
        $ownerIndex = '';
        $softDeleteColumn = '';

        if ($scoped) {
            $imports[] = "use App\\Models\\Traits\\OwnedByUser;\n";
            $traits[] = "    use OwnedByUser;\n";
            $notes = "\n    // `created_by` is stamped on create by OwnedByUser and is deliberately\n"
                ."    // NOT fillable — read the trait docblock before changing this.\n";
            $ownerColumn = "            \$table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();\n";
            $ownerIndex = "            \$table->index(['created_by', 'created_at']);\n";
        }

        if ($softDeletes) {
            $imports[] = "use Illuminate\\Database\\Eloquent\\SoftDeletes;\n";
            $traits[] = "    use SoftDeletes;\n";
            $softDeleteColumn = "            \$table->softDeletes();\n";
        }

        return [
            '{{ modelImports }}' => implode('', $imports),
            '{{ modelTraits }}' => implode('', $traits).$notes,
            '{{ table }}' => $table,
            '{{ ownerColumn }}' => $ownerColumn,
            '{{ ownerIndex }}' => $ownerIndex,
            '{{ softDeleteColumn }}' => $softDeleteColumn,
        ];
    }

    /**
     * Every `generated.*` key the three Vue stubs reference, with its English
     * default. StubDesignPassTest cross-checks this map against the rendered
     * pages, so a stub that grows a key without one here fails the suite.
     *
     * @return array<string,string>
     */
    public static function translationKeys(string $prefix, string $plural, string $singular): array
    {
        $lower = Str::lower($plural);

        return [
            "generated.{$prefix}.title" => $plural,
            "generated.{$prefix}.description" => "Manage {$lower}.",
            "generated.{$prefix}.add" => "Add {$singular}",
            "generated.{$prefix}.searchPlaceholder" => "Search {$lower}...",
            "generated.{$prefix}.section" => "{$singular} details",
            "generated.{$prefix}.createTitle" => "Create {$singular}",
            "generated.{$prefix}.createDescription" => 'Add a new '.Str::lower($singular).'.',
            "generated.{$prefix}.confirmCreate" => 'Create this '.Str::lower($singular).'?',
            "generated.{$prefix}.editTitle" => "Edit {$singular}",
            "generated.{$prefix}.editDescription" => 'Update '.Str::lower($singular).' details.',
            "generated.{$prefix}.confirmUpdate" => 'Save these changes?',
            "generated.{$prefix}.columns.name" => 'Name',
            "generated.{$prefix}.columns.createdAt" => 'Created',
        ];
    }
}
