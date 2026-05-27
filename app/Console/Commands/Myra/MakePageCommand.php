<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePageCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-page {name : Page name in PascalCase (e.g. Analytics)}
        {--icon= : lucide-vue-next icon for the nav item (default LayoutGrid)}
        {--group= : Sidebar group label (default Custom)}
        {--print : Print the route + nav snippets instead of editing source files}';

    protected $description = 'Scaffold a single admin page (controller + Vue page) and auto-wire its route, nav item, and view permission';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $prefix = Str::kebab($name);
        $print = (bool) $this->option('print');
        $icon = $this->option('icon') ?: config('myra.nav_icon', 'LayoutGrid');

        $repl = [
            '{{ model }}' => $name,
            '{{ modelVariable }}' => Str::camel($name),
        ];

        $this->writeStub('stubs/admin/controller.page.stub', "app/Http/Controllers/Admin/{$name}Controller.php", $repl);
        $this->writeStub('stubs/admin/page.single.stub', "resources/js/Pages/Admin/{$name}/Index.vue", $repl);

        $this->syncPermissions($prefix, ['view']);

        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}Controller";
        $route = "    Route::get('/{$prefix}', [{$fqcn}::class, 'index'])->middleware('permission:{$prefix}.view')->name('{$prefix}.index');";
        $this->registerRoute($route, "{$prefix}.index", $print);
        $this->registerNav($name, "admin.{$prefix}.index", $icon, "{$prefix}.view", $print);

        $this->newLine();
        $this->components->info("Page '{$name}' scaffolded → /admin/{$prefix} (run `npm run build`).");

        return self::SUCCESS;
    }
}
