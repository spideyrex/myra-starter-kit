<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeImportCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-import {name : Model name in PascalCase (e.g. Product)}
        {--print : Print the config snippet instead of editing config/myra.php}';

    protected $description = 'Scaffold an ImportDefinition for a model and register it under myra.imports.resources (served by the shared ImportController)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $repl = $this->replacements($name);
        $prefix = $repl['{{ routePrefix }}'];
        $print = (bool) $this->option('print');

        $this->writeRaw("app/Admin/Import/{$name}Import.php", $this->definition($name, $prefix));

        $this->syncPermissions($prefix, ['create']);

        $entry = "            '{$prefix}' => \\App\\Admin\\Import\\{$name}Import::class,";
        $this->registerImport($entry, $prefix, $print);

        $this->newLine();
        $this->components->info('Import scaffolded → POST '.\App\Support\Myra::adminPath("import/{$prefix}").'/preview | /validate | /commit');
        $this->components->bulletList([
            "Refine the columns, rules and resolveRecord() in app/Admin/Import/{$name}Import.php.",
            'authorizeRow() is mandatory — it runs per record, before any write.',
            "Use <ImportModal resource=\"{$prefix}\" ... /> on your index page.",
        ]);

        return self::SUCCESS;
    }

    /** Append the registry entry inside myra.imports.resources. */
    private function registerImport(string $entry, string $prefix, bool $print): void
    {
        $path = config_path('myra.php');
        $content = file_get_contents($path);

        if (str_contains($content, "'{$prefix}' => \\App\\Admin\\Import\\")) {
            $this->components->info("Registry entry for [{$prefix}] already present.");

            return;
        }

        if ($print || ! preg_match("/('resources' => \[\r?\n)/", $content)) {
            $this->components->warn('Add this to config/myra.php under myra.imports.resources:');
            $this->line($entry);

            return;
        }

        $content = preg_replace("/('resources' => \[\r?\n)/", "$1{$entry}\n", $content, 1);
        file_put_contents($path, $content);
        $this->components->info('Registered in config/myra.php.');
    }

    private function definition(string $name, string $prefix): string
    {
        return <<<PHP
<?php

namespace App\\Admin\\Import;

use App\\Models\\{$name};
use Illuminate\\Contracts\\Auth\\Authenticatable;
use Illuminate\\Database\\Eloquent\\Model;

final class {$name}Import
{
    public static function definition(): ImportDefinition
    {
        return ImportDefinition::make('{$prefix}')
            ->model({$name}::class)
            ->permission('{$prefix}.create')
            ->columns([
                ImportColumn::make('name')
                    ->label('Name')
                    ->requiredMapping()
                    ->guess(['title', 'label'])
                    ->rules(['required', 'string', 'max:255']),
                // TODO: declare the rest of your mappable columns.
            ])
            // MANDATORY: runs per record, before any write. An import must never be
            // a side door around the guards the single-record path applies.
            ->authorizeRow(fn (array \$row, ?Authenticatable \$actor) => (bool) \$actor?->can('{$prefix}.create'))
            ->resolveRecord(function (array \$row, ?Authenticatable \$actor): Model {
                \$record = {$name}::firstOrNew(['name' => \$row['name']]);
                \$record->fill(\$row);

                return \$record;
            });
    }
}

PHP;
    }
}
