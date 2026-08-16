<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakePageCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-page {name : Page name in PascalCase (e.g. Analytics)}
        {--icon= : lucide-vue-next icon for the nav item (default LayoutGrid)}
        {--group= : Sidebar group label (default config myra.nav_group)}
        {--print : Print every rendered file and snippet; write nothing}';

    protected $description = 'Scaffold a single admin page (controller + Vue page) and auto-wire its route, nav item, i18n keys and view permission';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $prefix = Str::kebab($name);
        $print = (bool) $this->option('print');
        $icon = $this->option('icon') ?: config('myra.nav_icon', 'LayoutGrid');
        $group = $this->option('group') ?: config('myra.nav_group', 'Custom');
        $label = Str::headline($name);

        $repl = [
            '{{ model }}' => $name,
            '{{ modelVariable }}' => Str::camel($name),
            '{{ routePrefix }}' => $prefix,
            '{{ permissionPrefix }}' => $prefix,
        ];

        $plan = [
            "app/Http/Controllers/Admin/{$name}Controller.php" => $this->renderStub('stubs/admin/controller.page.stub', $repl) ?? '',
            "resources/js/Pages/Admin/{$name}/Index.vue" => $this->renderStub('stubs/admin/page.single.stub', $repl) ?? '',
        ];

        $fqcn = "\\App\\Http\\Controllers\\Admin\\{$name}Controller";
        $route = "    Route::get('/{$prefix}', [{$fqcn}::class, 'index'])->middleware('permission:{$prefix}.view')->name('{$prefix}.index');";

        $translations = [
            "generated.{$prefix}.title" => $label,
            "generated.{$prefix}.description" => "Manage ".Str::lower($label).'.',
        ];

        if ($print) {
            foreach ($plan as $dest => $content) {
                $this->raw("===== {$dest} =====");
                $this->raw($content);
            }
            $this->raw('===== routes/web.php =====');
            $this->raw($route);
            $this->raw('===== i18n keys =====');
            foreach ($translations as $key => $value) {
                $this->raw("{$key} = {$value}");
            }
            $this->raw('===== nav =====');
            $this->raw("group={$group} icon={$icon} permission={$prefix}.view");

            return self::SUCCESS;
        }

        foreach ($plan as $dest => $content) {
            $this->writeRaw($dest, $content);
        }

        $this->addTranslations($translations);
        $this->syncPermissions($prefix, ['view']);
        $this->registerRoute($route, "{$prefix}.index", false);
        $this->registerNav($label, "admin.{$prefix}.index", $icon, "{$prefix}.view", false, $group);

        $this->newLine();
        $this->components->info("Page '{$name}' scaffolded → /admin/{$prefix} under the '{$group}' nav group (run `npm run build`).");

        return self::SUCCESS;
    }
}
