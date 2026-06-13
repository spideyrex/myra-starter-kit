<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeImportCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-import {name : Model name in PascalCase (e.g. Product)}
        {--print : Print the route snippet instead of editing routes/web.php}';

    protected $description = 'Scaffold a CSV import controller (preview + execute with column mapping) for a model + routes, gated by {prefix}.create';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $repl = $this->replacements($name);
        $prefix = $repl['{{ routePrefix }}'];
        $print = (bool) $this->option('print');

        $this->writeRaw("app/Http/Controllers/Admin/{$name}ImportController.php", $this->controller($name));

        $this->syncPermissions($prefix, ['create']);

        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}ImportController";
        $snippet = implode("\n", [
            "    // {$name} import",
            "    Route::post('/{$prefix}/import/preview', [{$fqcn}::class, 'preview'])->middleware('permission:{$prefix}.create')->name('{$prefix}.import-preview');",
            "    Route::post('/{$prefix}/import/execute', [{$fqcn}::class, 'execute'])->middleware('permission:{$prefix}.create')->name('{$prefix}.import-execute');",
        ]);
        $this->registerRoute($snippet, "{$prefix}.import-preview", $print);

        $this->newLine();
        $this->components->info("Import scaffolded → POST /admin/{$prefix}/import/preview + /execute");
        $this->components->bulletList([
            "Map CSV columns to model fields + add validation in {$name}ImportController::importRow().",
            "Use the ImportModal component on your index page, pointing at these routes.",
        ]);

        return self::SUCCESS;
    }

    private function controller(string $name): string
    {
        return <<<PHP
<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Models\\{$name};
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Validator;

class {$name}ImportController extends Controller
{
    /** Read CSV headers + a few sample rows for the mapping step. */
    public function preview(Request \$request): JsonResponse
    {
        \$request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        \$handle = fopen(\$request->file('file')->getRealPath(), 'r');
        \$headers = fgetcsv(\$handle) ?: [];
        \$headers[0] = preg_replace('/^\\x{FEFF}/u', '', \$headers[0] ?? '');
        \$headers = array_map('trim', \$headers);

        \$preview = [];
        \$rowCount = 0;
        while ((\$row = fgetcsv(\$handle)) !== false) {
            if (\$rowCount < 5) {
                \$preview[] = array_combine(\$headers, array_pad(\$row, count(\$headers), ''));
            }
            \$rowCount++;
        }
        fclose(\$handle);

        return response()->json(['headers' => \$headers, 'preview' => \$preview, 'total_rows' => \$rowCount]);
    }

    /** Create records from the uploaded CSV using the column mapping. */
    public function execute(Request \$request): JsonResponse
    {
        \$request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'mapping' => 'required|array',
        ]);

        \$mapping = \$request->mapping;
        \$handle = fopen(\$request->file('file')->getRealPath(), 'r');
        \$headers = fgetcsv(\$handle) ?: [];
        \$headers[0] = preg_replace('/^\\x{FEFF}/u', '', \$headers[0] ?? '');
        \$headers = array_map('trim', \$headers);

        \$imported = 0;
        \$errors = [];
        \$rowNum = 1;
        while ((\$row = fgetcsv(\$handle)) !== false) {
            \$rowNum++;
            \$data = array_combine(\$headers, array_pad(\$row, count(\$headers), ''));

            \$mapped = [];
            foreach (\$mapping as \$modelField => \$csvColumn) {
                if (\$csvColumn && isset(\$data[\$csvColumn])) {
                    \$mapped[\$modelField] = \$data[\$csvColumn];
                }
            }

            \$result = \$this->importRow(\$mapped, \$rowNum);
            \$result === true ? \$imported++ : \$errors[] = \$result;

            if (count(\$errors) >= 10) {
                break;
            }
        }
        fclose(\$handle);

        return response()->json(['imported' => \$imported, 'errors' => \$errors]);
    }

    /** Validate + create one record. Returns true on success or an error string. */
    private function importRow(array \$data, int \$rowNum): true|string
    {
        // TODO: define your validation rules + fillable mapping.
        \$validator = Validator::make(\$data, [
            // 'name' => 'required|string|max:255',
        ]);

        if (\$validator->fails()) {
            return "Row {\$rowNum}: " . implode(', ', \$validator->errors()->all());
        }

        {$name}::create(\$data);

        return true;
    }
}

PHP;
    }
}
