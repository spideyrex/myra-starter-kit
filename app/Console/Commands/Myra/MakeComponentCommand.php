<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeComponentCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-component {name : Page name in PascalCase (e.g. Student)}
        {component : Feature Demo keyword to clone (e.g. form-builder)}
        {--icon= : lucide-vue-next icon for the nav item (default LayoutGrid)}
        {--group= : Sidebar group label (default Custom)}
        {--print : Print the route + nav snippets instead of editing source files}';

    protected $description = 'Scaffold an admin page by cloning a Feature Demo component (form-builder, data-table, wizard, infolist, widgets, …) with auto-wired route, nav, and permission';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $component = Str::kebab($this->argument('component'));
        $prefix = Str::kebab($name);
        $print = (bool) $this->option('print');
        $icon = $this->option('icon') ?: config('myra.nav_icon', 'LayoutGrid');

        $components = config('myra.components', []);
        if (! isset($components[$component])) {
            $this->error("Unknown component '{$component}'.");
            $this->line('Available: '.implode(', ', array_keys($components)));

            return self::FAILURE;
        }

        $def = $components[$component];

        $repl = [
            '{{ model }}' => $name,
            '{{ modelVariable }}' => Str::camel($name),
            '{{ modelPlural }}' => $name,
            '{{ routePrefix }}' => $prefix,
            '{{ permissionPrefix }}' => $prefix,
        ];

        // Clone the demo Vue page and generate a matching controller.
        $this->cloneDemo($def['demo'], "resources/js/Pages/Admin/{$name}/Index.vue", $repl);
        $this->writeRaw("app/Http/Controllers/Admin/{$name}Controller.php", $this->buildController($name, $def));

        $this->syncPermissions($prefix, ['view']);

        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}Controller";
        $route = "    Route::get('/{$prefix}', [{$fqcn}::class, 'index'])->middleware('permission:{$prefix}.view')->name('{$prefix}.index');";
        $this->registerRoute($route, "{$prefix}.index", $print);
        $this->registerNav($name, "admin.{$prefix}.index", $icon, "{$prefix}.view", $print);

        $this->newLine();
        $this->components->info("'{$name}' scaffolded from '{$component}' demo → ".\App\Support\Myra::adminPath($prefix).' (run `npm run build`).');
        $this->components->warn('The page ships with sample data — wire it to your model when ready.');

        return self::SUCCESS;
    }

    /** Build a controller that renders the cloned page with the right props. */
    private function buildController(string $name, array $def): string
    {
        $method = $def['method'] ?? null;
        $needsRequest = (bool) ($def['request'] ?? false);

        $uses = [
            'use App\\Http\\Controllers\\Controller;',
            'use Inertia\\Inertia;',
            'use Inertia\\Response;',
        ];

        if ($method === null) {
            $signature = 'public function index(): Response';
            $render = "        return Inertia::render('Admin/{$name}/Index');";
        } else {
            $uses[] = 'use App\\Admin\\Traits\\HasSampleData;';
            if ($needsRequest) {
                array_splice($uses, 1, 0, 'use Illuminate\\Http\\Request;');
                $signature = 'public function index(Request $request): Response';
                $render = "        return Inertia::render('Admin/{$name}/Index', \$this->{$method}(\$request));";
            } else {
                $signature = 'public function index(): Response';
                $render = "        return Inertia::render('Admin/{$name}/Index', \$this->{$method}());";
            }
        }

        sort($uses);
        $usesBlock = implode("\n", $uses);
        $traitLine = $method !== null ? "    use HasSampleData;\n\n" : '';

        return <<<PHP
<?php

namespace App\\Http\\Controllers\\Admin;

{$usesBlock}

class {$name}Controller extends Controller
{
{$traitLine}    {$signature}
    {
{$render}
    }
}

PHP;
    }
}
