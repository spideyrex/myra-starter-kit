<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use App\Console\Commands\Myra\Concerns\ScaffoldsNavigation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeNestedCommand extends Command
{
    use ScaffoldsAdmin, ScaffoldsNavigation;

    protected $signature = 'make:myra-nested {name : Child model in PascalCase (e.g. Lesson)}
        {--parent= : Parent model in PascalCase (e.g. Course)}
        {--relationship= : hasMany name on the parent (default plural camel of name)}
        {--inverse= : belongsTo name on the child (default camel of parent)}
        {--foreign-key= : Foreign key column (default {parent}_id)}
        {--cluster= : Cluster slug the routes live under (e.g. learning)}
        {--print : Print the route snippet instead of editing source files}';

    protected $description = 'Scaffold a nested resource: /admin/{parent}/{id}/{children} with scoped route bindings';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $parent = Str::studly((string) $this->option('parent'));

        if ($parent === '') {
            $this->error('--parent is required (e.g. --parent=Course).');

            return self::FAILURE;
        }

        $plural = Str::plural($name);
        $childVar = Str::camel($name);
        $relationship = $this->option('relationship') ?: Str::camel($plural);
        $inverse = $this->option('inverse') ?: Str::camel($parent);
        $foreignKey = $this->option('foreign-key') ?: Str::snake($parent).'_id';
        $print = (bool) $this->option('print');

        $clusterSlug = $this->option('cluster') ? Str::kebab($this->option('cluster')) : '';
        $parentSegment = Str::kebab(Str::plural($parent));
        $parentPrefix = $clusterSlug === '' ? $parentSegment : "{$clusterSlug}.{$parentSegment}";
        $parentPath = str_replace('.', '/', $parentPrefix);

        $routePrefix = Str::kebab($plural);
        $permissionPrefix = $routePrefix;

        $repl = array_merge($this->replacements($name), [
            '{{ parentModel }}' => $parent,
            '{{ parentRoutePrefix }}' => $parentPrefix,
            '{{ parentPermission }}' => $parentSegment,
            '{{ relationship }}' => $relationship,
            '{{ inverse }}' => $inverse,
            '{{ foreignKey }}' => $foreignKey,
        ]);

        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}Controller";
        $base = "/{$parentPath}/{{$inverse}}/{$relationship}";

        $routes = <<<PHP
                Route::get('{$base}', [{$fqcn}::class, 'index'])
                    ->middleware('permission:{$permissionPrefix}.view')->scopeBindings()->name('{$parentPrefix}.{$relationship}.index');
                Route::post('{$base}', [{$fqcn}::class, 'store'])
                    ->middleware('permission:{$permissionPrefix}.create')->scopeBindings()->name('{$parentPrefix}.{$relationship}.store');
                Route::delete('{$base}/{{$childVar}}', [{$fqcn}::class, 'destroy'])
                    ->middleware('permission:{$permissionPrefix}.delete')->scopeBindings()->name('{$parentPrefix}.{$relationship}.destroy');
            PHP;

        if (! $print) {
            $this->writeStub('stubs/nested/controller.nested.stub', "app/Http/Controllers/Admin/{$name}Controller.php", $repl);
            $this->writeStub('stubs/nested/page.nested-index.stub', "resources/js/Pages/Admin/{$plural}/Index.vue", $repl);
            $this->syncPermissions($permissionPrefix, ['view', 'create', 'delete']);
            $this->writeNavLocaleKeys([
                "clusters.{$routePrefix}.label" => Str::headline($plural),
                "clusters.{$routePrefix}.description" => 'Belongs to {parent}.',
                "clusters.{$routePrefix}.searchPlaceholder" => "Search {$routePrefix}...",
                "clusters.{$routePrefix}.name" => 'Name',
                "clusters.{$routePrefix}.namePlaceholder" => 'Name',
                "clusters.{$routePrefix}.created" => 'Created',
                "clusters.{$routePrefix}.add" => 'Add '.Str::lower(Str::headline($name)),
                "clusters.{$routePrefix}.delete" => 'Delete',
                "clusters.{$routePrefix}.deleteConfirm" => 'Delete this record? This cannot be undone.',
            ]);
        }

        $this->registerRoute($routes, "{$parentPrefix}.{$relationship}.index", $print);

        $this->newLine();
        $this->components->info("Nested resource '{$name}' scaffolded under '{$parentPrefix}' (run `npm run build`).");
        $this->line("The parent resolves through its own global scopes, so an out-of-scope {$parent} is a 404, not a readable id.");

        return self::SUCCESS;
    }
}
