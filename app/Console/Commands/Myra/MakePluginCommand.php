<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePluginCommand extends Command
{
    use ScaffoldsAdmin;

    // No braces inside a description: the signature parser splits on them.
    protected $signature = 'make:myra-plugin {name : PascalCase, e.g. Billing}
        {--id= : Plugin id, defaults to the kebab-case name}
        {--path= : Destination directory, defaults to plugins/<kebab-name>}
        {--namespace= : PSR-4 root, defaults to App\\Plugins\\<Name>}
        {--print : Print what would be written; touch nothing}';

    protected $description = 'Scaffold a Myra plugin (one class, one manifest) and register it in config/myra.php';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $kebab = Str::kebab($name);
        $id = $this->option('id') ?: $kebab;
        $path = trim((string) ($this->option('path') ?: "plugins/{$kebab}"), '/');
        $namespace = trim((string) ($this->option('namespace') ?: "App\\Plugins\\{$name}"), '\\');
        $print = (bool) $this->option('print');

        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $id)) {
            $this->error("Plugin id [{$id}] must be kebab-case (lowercase letters, digits and single hyphens).");

            return self::FAILURE;
        }

        $repl = [
            '{{ name }}' => $name,
            '{{ id }}' => $id,
            '{{ namespace }}' => $namespace,
            '{{ namespaceEscaped }}' => str_replace('\\', '\\\\', $namespace),
            '{{ vendor }}' => Str::kebab(Str::before($namespace, '\\')) ?: 'myra',
            '{{ version }}' => (string) config('myra.version', '2.4.0'),
        ];

        $files = [
            "{$path}/src/{$name}Plugin.php" => 'stubs/plugin/plugin.stub',
            "{$path}/composer.json" => 'stubs/plugin/composer.json.stub',
            "{$path}/tests/{$name}PluginTest.php" => 'stubs/plugin/test.stub',
        ];

        $fqcn = "{$namespace}\\{$name}Plugin";

        if ($print) {
            $this->line("Would create under {$path}/:");
            foreach (array_keys($files) as $dest) {
                $this->line("  {$dest}");
            }
            foreach (self::LOCALES as $locale) {
                $this->line("  {$path}/lang/{$locale}.json");
            }
            $this->line("  {$path}/database/migrations/.gitkeep");
            $this->line("  {$path}/resources/js/Pages/.gitkeep");
            $this->newLine();
            $this->line("Would register: \\{$fqcn}::class in config('myra.extensions.plugins')");

            return self::SUCCESS;
        }

        foreach ($files as $dest => $stub) {
            $this->writeStub($stub, $dest, $repl);
        }

        foreach (self::LOCALES as $locale) {
            $this->writeStub('stubs/plugin/lang.json.stub', "{$path}/lang/{$locale}.json", $repl);
        }

        $this->writeRaw("{$path}/database/migrations/.gitkeep", '');
        $this->writeRaw("{$path}/resources/js/Pages/.gitkeep", '');

        $this->registerPluginClass($fqcn);
        $this->registerAutoload($namespace, $path);

        $this->newLine();
        $this->components->info("Plugin '{$name}' scaffolded → {$path} (id: {$id}).");
        $this->line('  1. composer dump-autoload   <fg=yellow>(required before the next request — an unautoloadable plugin class fails to register)</>');
        $this->line("  2. Declare routes/reports/imports in {$path}/src/{$name}Plugin.php");
        $this->line('  3. php artisan shield:generate   (creates the plugin\'s permissions)');

        return self::SUCCESS;
    }

    /** Add a PSR-4 entry to the root composer.json when the plugin lives in-repo. */
    private function registerAutoload(string $namespace, string $path): void
    {
        $file = base_path('composer.json');
        $json = json_decode(file_get_contents($file), true);

        if (! is_array($json)) {
            $this->warn('composer.json is not valid JSON; add the PSR-4 entry by hand.');

            return;
        }

        $key = $namespace.'\\';

        if (isset($json['autoload']['psr-4'][$key])) {
            return;
        }

        $json['autoload']['psr-4'][$key] = "{$path}/src/";

        file_put_contents(
            $file,
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        $this->info("Autoload: {$key} => {$path}/src/ added to composer.json.");
    }
}
