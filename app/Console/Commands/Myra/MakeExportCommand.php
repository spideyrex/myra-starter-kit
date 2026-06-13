<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeExportCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-export {name : Model name in PascalCase (e.g. Product)}
        {--print : Print the route snippet instead of editing routes/web.php}';

    protected $description = 'Scaffold a streaming CSV export controller for a model (uses the ExportableQuery trait) + route, gated by {prefix}.view';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $repl = $this->replacements($name);
        $prefix = $repl['{{ routePrefix }}'];
        $print = (bool) $this->option('print');

        $this->writeRaw("app/Http/Controllers/Admin/{$name}ExportController.php", $this->controller($name, $prefix));

        // Ensure the view permission exists (idempotent if the resource already created it).
        $this->syncPermissions($prefix, ['view']);

        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}ExportController";
        $route = "    Route::get('/{$prefix}/export-csv', {$fqcn}::class)->middleware(['permission:{$prefix}.view', 'throttle:5,1'])->name('{$prefix}.export-csv');";
        $this->registerRoute($route, "{$prefix}.export-csv", $print);

        $this->newLine();
        $this->components->info("Export scaffolded → GET /admin/{$prefix}/export-csv");
        $this->components->bulletList([
            "Customize the headers + row mapper in {$name}ExportController.",
            "Add an export button to your index page:",
            "  <Button as-child><Link :href=\"route('admin.{$prefix}.export-csv')\">Export CSV</Link></Button>",
        ]);

        return self::SUCCESS;
    }

    private function controller(string $name, string $prefix): string
    {
        return <<<PHP
<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Admin\\Traits\\ExportableQuery;
use App\\Http\\Controllers\\Controller;
use App\\Models\\{$name};
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\StreamedResponse;

class {$name}ExportController extends Controller
{
    use ExportableQuery;

    public function __invoke(Request \$request): StreamedResponse
    {
        // TODO: customize the columns + row mapping for your model.
        return \$this->streamCsvExport(
            {$name}::query(),
            ['ID', 'Created At'],
            fn (\$record) => [
                \$record->id,
                optional(\$record->created_at)->toDateTimeString(),
            ],
            '{$prefix}.csv',
        );
    }
}

PHP;
    }
}
