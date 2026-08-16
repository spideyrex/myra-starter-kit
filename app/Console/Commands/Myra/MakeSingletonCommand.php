<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use App\Console\Commands\Myra\Concerns\ScaffoldsNavigation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSingletonCommand extends Command
{
    use ScaffoldsAdmin, ScaffoldsNavigation;

    protected $signature = 'make:myra-singleton {name : Model name in PascalCase (e.g. SiteIdentity)}
        {--cluster= : Cluster slug the routes live under (e.g. learning)}
        {--icon= : lucide-vue-next icon for the nav item}
        {--print : Print the route snippet instead of editing source files}';

    protected $description = 'Scaffold a singular resource: one record, GET + PUT, no create and no destroy';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $print = (bool) $this->option('print');

        $clusterSlug = $this->option('cluster') ? Str::kebab($this->option('cluster')) : '';
        $segment = Str::kebab($name);
        $routePrefix = $clusterSlug === '' ? $segment : "{$clusterSlug}.{$segment}";
        $routePath = str_replace('.', '/', $routePrefix);
        $permissionPrefix = $segment;

        $repl = array_merge($this->replacements($name), [
            '{{ routePrefix }}' => $routePrefix,
            '{{ permissionPrefix }}' => $permissionPrefix,
        ]);

        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}Controller";

        $routes = <<<PHP
                Route::get('/{$routePath}', [{$fqcn}::class, 'show'])
                    ->middleware('permission:{$permissionPrefix}.view')->name('{$routePrefix}.show');
                Route::put('/{$routePath}', [{$fqcn}::class, 'update'])
                    ->middleware('permission:{$permissionPrefix}.edit')->name('{$routePrefix}.update');
            PHP;

        if (! $print) {
            $this->writeStub('stubs/singular/controller.singular.stub', "app/Http/Controllers/Admin/{$name}Controller.php", $repl);
            $this->writeStub('stubs/singular/page.singular.stub', "resources/js/Pages/Admin/{$name}/Edit.vue", $repl);
            $this->syncPermissions($permissionPrefix, ['view', 'edit']);
            $this->writeNavLocaleKeys([
                "clusters.{$routePrefix}.label" => Str::headline($name),
                "clusters.{$routePrefix}.description" => 'A singular resource: one record, two routes.',
                "clusters.{$routePrefix}.note" => 'The first save creates the record; every later save updates the same row.',
                "clusters.{$routePrefix}.name" => 'Name',
                "clusters.{$routePrefix}.save" => 'Save',
            ]);
        }

        $this->registerRoute($routes, "{$routePrefix}.show", $print);

        $this->newLine();
        $this->components->info("Singular resource '{$name}' scaffolded → ".\App\Support\Myra::adminPath($routePath).' (run `npm run build`).');
        $this->line('Create the model and its migration next; the page renders with null values until the first save.');

        return self::SUCCESS;
    }
}
