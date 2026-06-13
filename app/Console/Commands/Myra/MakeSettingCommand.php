<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeSettingCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-setting {name : Settings group name in PascalCase (e.g. Billing)}
        {--icon= : lucide-vue-next icon for the nav item (default Settings2)}
        {--print : Print the route + nav snippets instead of editing source files}';

    protected $description = 'Scaffold a Spatie settings group (Settings class + seeding migration + admin settings page) with auto-wired route, nav, and view/edit permissions';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $group = Str::kebab($name);            // settings group key, e.g. "billing"
        $prefix = "{$group}-settings";          // route + permission prefix, e.g. "billing-settings"
        $var = Str::camel($name);
        $print = (bool) $this->option('print');
        $icon = $this->option('icon') ?: 'Settings2';

        $repl = [
            '{{ model }}' => $name,
            '{{ modelVariable }}' => $var,
            '{{ routePrefix }}' => $prefix,
        ];

        // 1. Settings class
        $this->writeRaw("app/Settings/{$name}Settings.php", $this->settingsClass($name, $group));

        // 2. Seeding migration
        $migration = date('Y_m_d_His') . "_add_{$group}_settings.php";
        $this->writeRaw("database/migrations/{$migration}", $this->migration($group));

        // 3. Controller
        $this->writeRaw("app/Http/Controllers/Admin/{$name}SettingsController.php", $this->controller($name));

        // 4. Vue page
        $this->writeStub('stubs/admin/settings.page.stub', "resources/js/Pages/Admin/{$name}Settings/Index.vue", $repl);

        // 5. Permissions (view + edit), granted to admin, registered in shield config
        $this->syncPermissions($prefix, ['view', 'edit']);

        // 6. Routes (index + update) and nav
        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}SettingsController";
        $snippet = implode("\n", [
            "    // {$name} Settings",
            "    Route::get('/{$prefix}', [{$fqcn}::class, 'index'])->middleware('permission:{$prefix}.view')->name('{$prefix}.index');",
            "    Route::put('/{$prefix}', [{$fqcn}::class, 'update'])->middleware('permission:{$prefix}.edit')->name('{$prefix}.update');",
        ]);
        $this->registerRoute($snippet, "{$prefix}.index", $print);
        $this->registerNav("{$name} Settings", "admin.{$prefix}.index", $icon, "{$prefix}.view", $print);

        $this->newLine();
        $this->components->info("Settings group '{$name}' scaffolded → /admin/{$prefix}");
        $this->components->warn("Run `php artisan migrate` to seed defaults, then `npm run build`.");

        return self::SUCCESS;
    }

    private function settingsClass(string $name, string $group): string
    {
        return <<<PHP
<?php

namespace App\\Settings;

use Spatie\\LaravelSettings\\Settings;

class {$name}Settings extends Settings
{
    public bool \$enabled;
    public string \$label;

    public static function group(): string
    {
        return '{$group}';
    }
}

PHP;
    }

    private function migration(string $group): string
    {
        return <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;

return new class extends Migration
{
    public function up(): void
    {
        \$settings = [
            ['group' => '{$group}', 'name' => 'enabled', 'payload' => json_encode(false)],
            ['group' => '{$group}', 'name' => 'label', 'payload' => json_encode('')],
        ];

        foreach (\$settings as \$setting) {
            DB::table('settings')->updateOrInsert(
                ['group' => \$setting['group'], 'name' => \$setting['name']],
                array_merge(\$setting, ['locked' => false, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('group', '{$group}')->delete();
    }
};

PHP;
    }

    private function controller(string $name): string
    {
        return <<<PHP
<?php

namespace App\\Http\\Controllers\\Admin;

use App\\Http\\Controllers\\Controller;
use App\\Settings\\{$name}Settings;
use Illuminate\\Http\\RedirectResponse;
use Illuminate\\Http\\Request;
use Inertia\\Inertia;
use Inertia\\Response;

class {$name}SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/{$name}Settings/Index', [
            'settings' => app({$name}Settings::class)->toArray(),
        ]);
    }

    public function update(Request \$request): RedirectResponse
    {
        \$validated = \$request->validate([
            'enabled' => ['required', 'boolean'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        \$settings = app({$name}Settings::class);
        \$settings->enabled = \$request->boolean('enabled');
        \$settings->label = \$validated['label'] ?? '';
        \$settings->save();

        return back()->with('success', '{$name} settings updated successfully.');
    }
}

PHP;
    }
}
