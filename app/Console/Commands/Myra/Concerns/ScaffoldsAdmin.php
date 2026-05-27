<?php

namespace App\Console\Commands\Myra\Concerns;

use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared helpers for the make:myra-* scaffolding commands: stub rendering,
 * demo cloning, permission generation, and idempotent route/nav wiring.
 */
trait ScaffoldsAdmin
{
    /** Token replacements derived from a PascalCase model/page name. */
    protected function replacements(string $name): array
    {
        $name = Str::studly($name);
        $plural = Str::plural($name);

        return [
            '{{ model }}' => $name,
            '{{ modelVariable }}' => Str::camel($name),
            '{{ modelPlural }}' => $plural,
            '{{ modelPluralVariable }}' => Str::camel($plural),
            '{{ routePrefix }}' => Str::kebab($plural),
            '{{ permissionPrefix }}' => Str::kebab($plural),
        ];
    }

    /** Render a stub to a destination. Returns false if the file already exists. */
    protected function writeStub(string $stubRel, string $destRel, array $replacements): bool
    {
        $stub = base_path($stubRel);

        if (! file_exists($stub)) {
            $this->error("Stub not found: {$stubRel}");
            return false;
        }

        $content = str_replace(array_keys($replacements), array_values($replacements), file_get_contents($stub));

        return $this->writeRaw($destRel, $content);
    }

    /** Write raw content to a destination, creating directories. Skips if it exists. */
    protected function writeRaw(string $destRel, string $content): bool
    {
        $dest = base_path($destRel);

        if (file_exists($dest)) {
            $this->warn("Already exists, skipping: {$destRel}");
            return false;
        }

        if (! is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0755, true);
        }

        file_put_contents($dest, $content);
        $this->info("Created: {$destRel}");

        return true;
    }

    /** Clone a Feature Demo Vue page into a new admin page, rewriting references. */
    protected function cloneDemo(string $demoBasename, string $destRel, array $replacements): bool
    {
        $source = base_path("resources/js/Pages/Admin/Demo/{$demoBasename}.vue");

        if (! file_exists($source)) {
            $this->error("Demo not found: {$demoBasename}.vue");
            return false;
        }

        $content = file_get_contents($source);
        $prefix = $replacements['{{ routePrefix }}'];
        $title = $replacements['{{ modelPlural }}'];

        // Point every demo route reference at the new page's index route so Ziggy resolves.
        $content = preg_replace('/admin\.demo\.[a-z0-9-]+/', "admin.{$prefix}.index", $content);
        // Best-effort title/breadcrumb rewrites (cosmetic).
        $content = preg_replace('/<Head title="[^"]*"/', '<Head title="' . $title . '"', $content, 1);
        $content = preg_replace("/:breadcrumbs=\"\[\{ label: '[^']*' \}\]\"/", ":breadcrumbs=\"[{ label: '{$title}' }]\"", $content, 1);
        $content = preg_replace('/(<PageHeader\b[^>]*\btitle=")[^"]*"/s', '${1}' . $title . '"', $content, 1);

        return $this->writeRaw($destRel, $content);
    }

    /** Create "{prefix}.{ability}" permissions, grant to admin, and register the module in config/shield.php. */
    protected function syncPermissions(string $prefix, array $abilities): void
    {
        $permissionModel = config('permission.models.permission', \Spatie\Permission\Models\Permission::class);
        $roleModel = config('permission.models.role', \Spatie\Permission\Models\Role::class);
        $guard = config('auth.defaults.guard', 'web');

        $names = [];
        foreach ($abilities as $ability) {
            $name = "{$prefix}.{$ability}";
            $names[] = $name;
            $permissionModel::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        $admin = $roleModel::firstOrCreate(['name' => config('shield.admin_role', 'admin'), 'guard_name' => $guard]);
        $admin->givePermissionTo($names);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $this->info('Permissions: ' . implode(', ', $names) . ' (granted to admin; super-admin always allowed).');

        $this->registerShieldModule($prefix, $abilities);
    }

    /** Add the module to config/shield.php at the myra:modules marker (idempotent). */
    protected function registerShieldModule(string $prefix, array $abilities): void
    {
        $file = base_path('config/shield.php');
        if (! file_exists($file)) {
            return;
        }

        $content = file_get_contents($file);
        if (str_contains($content, "'{$prefix}' =>")) {
            return; // already registered
        }

        $list = "'" . implode("', '", $abilities) . "'";
        $line = "        '{$prefix}' => [{$list}],\n        /* myra:modules */";
        $content = str_replace('/* myra:modules */', $line, $content);
        file_put_contents($file, $content);
    }

    /** Insert a route line into routes/web.php at the myra:routes marker, or print it. */
    protected function registerRoute(string $snippet, string $routeName, bool $print): void
    {
        if ($print) {
            $this->line($snippet);
            return;
        }

        $file = base_path('routes/web.php');
        $content = file_get_contents($file);

        if (str_contains($content, "->name('{$routeName}')")) {
            $this->warn("Route {$routeName} already present, skipping.");
            return;
        }

        $content = str_replace('// myra:routes', rtrim($snippet) . "\n\n    // myra:routes", $content);
        file_put_contents($file, $content);
        $this->info("Route registered: {$routeName}");
    }

    /** Insert a nav item into AuthenticatedLayout.vue at the myra:nav marker, or print it. */
    protected function registerNav(string $title, string $routeName, string $icon, ?string $permission, bool $print): void
    {
        $perm = $permission ? "'{$permission}'" : 'null';
        $item = "{ title: '{$title}', href: route('{$routeName}'), icon: {$icon}, permission: {$perm} },";

        if ($print) {
            $this->line('Add to a nav group in AuthenticatedLayout.vue:');
            $this->line('            ' . $item);
            return;
        }

        $file = base_path('resources/js/Layouts/AuthenticatedLayout.vue');
        $content = file_get_contents($file);

        if (str_contains($content, "route('{$routeName}')")) {
            $this->warn("Nav item for {$routeName} already present, skipping.");
            return;
        }

        $this->ensureIconImported($content, $icon);
        $content = str_replace('/* myra:nav */', $item . "\n            /* myra:nav */", $content);
        file_put_contents($file, $content);
        $this->info("Nav item added: {$title}");
    }

    /** Ensure a lucide icon is imported in the layout (mutates $content by reference). */
    private function ensureIconImported(string &$content, string $icon): void
    {
        if (preg_match('/\b' . preg_quote($icon, '/') . '\b[^;]*from \'lucide-vue-next\'/s', $content)) {
            return;
        }
        // Add the icon to the lucide-vue-next import block.
        $content = preg_replace(
            "/\n\} from 'lucide-vue-next';/",
            "\n    {$icon},\n} from 'lucide-vue-next';",
            $content,
            1
        );
    }
}
