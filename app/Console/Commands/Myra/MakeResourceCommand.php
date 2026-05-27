<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeResourceCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-resource {name : Model name in PascalCase (e.g. Product)}
        {--model : Also generate the Eloquent model + migration (make:model -m)}
        {--icon= : lucide-vue-next icon for the nav item (default LayoutGrid)}
        {--group= : Sidebar group label (default Custom)}
        {--print : Print the route + nav snippets instead of editing source files}';

    protected $description = 'Scaffold a full admin CRUD resource (controller, service, 3 Vue pages) and auto-wire routes, nav, and view/create/edit/delete permissions';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $repl = $this->replacements($name);
        $plural = $repl['{{ modelPlural }}'];
        $prefix = $repl['{{ routePrefix }}'];
        $var = $repl['{{ modelVariable }}'];
        $print = (bool) $this->option('print');
        $icon = $this->option('icon') ?: config('myra.nav_icon', 'LayoutGrid');

        if ($this->option('model')) {
            $this->call('make:model', ['name' => $name, '-m' => true]);
        }

        $this->writeStub('stubs/admin/controller.resource.stub', "app/Http/Controllers/Admin/{$name}Controller.php", $repl);
        $this->writeStub('stubs/admin/service.stub', "app/Services/{$name}Service.php", $repl);
        $this->writeStub('stubs/admin/page.index.stub', "resources/js/Pages/Admin/{$plural}/Index.vue", $repl);
        $this->writeStub('stubs/admin/page.create.stub', "resources/js/Pages/Admin/{$plural}/Create.vue", $repl);
        $this->writeStub('stubs/admin/page.edit.stub', "resources/js/Pages/Admin/{$plural}/Edit.vue", $repl);

        $this->syncPermissions($prefix, ['view', 'create', 'edit', 'delete']);

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
        $this->registerRoute($snippet, "{$prefix}.index", $print);
        $this->registerNav($plural, "admin.{$prefix}.index", $icon, "{$prefix}.view", $print);

        $this->newLine();
        if (! $this->option('model')) {
            $this->components->warn("Remember to create the model: php artisan make:model {$name} -m");
        }
        $this->components->info("Resource '{$name}' scaffolded → /admin/{$prefix} (run `npm run build`).");

        return self::SUCCESS;
    }
}
