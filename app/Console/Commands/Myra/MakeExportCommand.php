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

    protected $description = 'Scaffold a streaming CSV/XLSX export controller for a model (ExportDefinition + ExportableQuery) + route, gated by {prefix}.view';

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
            "Declare your columns on the ExportDefinition in {$name}ExportController.",
            'Pass the SAME scoped query your index uses — the definition adds columns, never rows.',
            'Add <ExportDropdown :csv-route="\'admin.' . $prefix . '.export-csv\'" ... /> to your index page.',
        ]);

        return self::SUCCESS;
    }

    private function controller(string $name, string $prefix): string
    {
        return <<<PHP
<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Admin\\Export\\ExportColumn;
use App\\Admin\\Export\\ExportDefinition;
use App\\Admin\\Traits\\ExportableQuery;
use App\\Http\\Controllers\\Controller;
use App\\Models\\{$name};
use Illuminate\\Support\\Facades\\Gate;
use Illuminate\\Http\\Request;
use Symfony\\Component\\HttpFoundation\\StreamedResponse;

class {$name}ExportController extends Controller
{
    use ExportableQuery;

    public function __invoke(Request \$request): StreamedResponse
    {
        Gate::authorize('{$prefix}.view');

        // Pass the same scoped + filtered query your index uses, so the export can
        // never return a row the listing would not.
        return \$this->streamExport(
            {$name}::query(),
            ExportDefinition::make('{$prefix}')
                ->columns([
                    ExportColumn::make('id')->label('ID'),
                    ExportColumn::make('created_at')->label('Created At')->date('Y-m-d H:i'),
                ])
                ->formats(['csv', 'xlsx'])
                ->filename('{$prefix}'),
            \$request,
        );
    }
}

PHP;
    }
}
