<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Composer\Autoload\ClassLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

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

        // Order matters: the class must be autoloadable BEFORE it is declared in
        // config, or the next boot resolves an unknown class.
        $this->registerAutoload($namespace, $path);

        if ($this->dumpAutoload() && $this->isResolvable($fqcn, $namespace, $path)) {
            $this->registerPluginClass($fqcn);
        } else {
            $this->warn('Skipped registering the plugin in config/myra.php: the class is not autoloadable yet.');
            $this->warn("Run `composer dump-autoload`, then add \\{$fqcn}::class to myra.extensions.plugins.");
        }

        $this->newLine();
        $this->components->info("Plugin '{$name}' scaffolded → {$path} (id: {$id}).");
        $this->line("  1. Declare routes/reports/imports in {$path}/src/{$name}Plugin.php");
        $this->line('  2. php artisan shield:generate   (creates the plugin\'s permissions)');

        return self::SUCCESS;
    }

    /**
     * Prove the class really loads before config declares it. The running
     * ClassLoader predates the dump, so teach it the new prefix first.
     */
    private function isResolvable(string $fqcn, string $namespace, string $path): bool
    {
        $loader = require base_path('vendor/autoload.php');

        if ($loader instanceof ClassLoader) {
            $loader->addPsr4($namespace.'\\', base_path("{$path}/src"));
        }

        return class_exists($fqcn);
    }

    /** Regenerate the autoloader so the new class is resolvable on the next boot. */
    private function dumpAutoload(): bool
    {
        $command = $this->composerCommand();

        if ($command === null) {
            $this->warn('composer executable not found; cannot refresh the autoloader.');

            return false;
        }

        $process = new Process([...$command, 'dump-autoload'], base_path(), null, null, 300);
        $process->run(fn ($type, $buffer) => $this->output->write($buffer));

        if (! $process->isSuccessful()) {
            $this->warn('composer dump-autoload failed.');

            return false;
        }

        return true;
    }

    /**
     * Prefer composer.phar run through the PHP binary already executing artisan:
     * a `composer` shim on PATH may point at a different, older PHP and then
     * fail composer's own platform check.
     *
     * @return array<int,string>|null
     */
    private function composerCommand(): ?array
    {
        $phars = [base_path('composer.phar')];

        $executable = (new ExecutableFinder)->find('composer');

        if ($executable !== null) {
            $phars[] = dirname($executable).DIRECTORY_SEPARATOR.'composer.phar';
        }

        foreach ($phars as $phar) {
            if (is_file($phar)) {
                return [PHP_BINARY, $phar];
            }
        }

        return $executable === null ? null : [$executable];
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
