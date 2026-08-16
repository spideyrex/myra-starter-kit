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

    /** Write a line verbatim — the console formatter must not touch generated markup. */
    protected function raw(string $text): void
    {
        $this->getOutput()->writeln($text, \Symfony\Component\Console\Output\OutputInterface::OUTPUT_RAW);
    }

    /** Render a stub with its replacements applied. Null when the stub is missing. */
    protected function renderStub(string $stubRel, array $replacements): ?string
    {
        $stub = base_path($stubRel);

        if (! file_exists($stub)) {
            $this->error("Stub not found: {$stubRel}");
            return null;
        }

        return str_replace(array_keys($replacements), array_values($replacements), file_get_contents($stub));
    }

    /** Render a stub to a destination. Returns false if the file already exists. */
    protected function writeStub(string $stubRel, string $destRel, array $replacements): bool
    {
        $content = $this->renderStub($stubRel, $replacements);

        if ($content === null) {
            return false;
        }

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

    /**
     * Insert a nav item into AuthenticatedLayout.vue, or print it. `$group` is
     * the sidebar group label: null (or the default) uses the existing
     * `myra:nav` marker; anything else gets its own group with its own marker.
     */
    protected function registerNav(string $title, string $routeName, string $icon, ?string $permission, bool $print, ?string $group = null): void
    {
        $perm = $permission ? "'{$permission}'" : 'null';
        $item = "{ title: '{$title}', href: route('{$routeName}'), icon: {$icon}, permission: {$perm} },";

        if ($print) {
            $this->line('Add to a nav group in AuthenticatedLayout.vue'.($group ? " ({$group})" : '').':');
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

        $marker = $this->navMarkerFor($content, $group);
        $content = str_replace($marker, $item . "\n            " . $marker, $content);

        file_put_contents($file, $content);
        $this->info("Nav item added: {$title}" . ($group ? " (group: {$group})" : ''));
    }

    /**
     * Resolve the marker a nav item should be inserted at, creating the group
     * block on first use. Mutates $content by reference.
     */
    private function navMarkerFor(string &$content, ?string $group): string
    {
        $default = config('myra.nav_group', 'Custom');

        if ($group === null || $group === $default) {
            return '/* myra:nav */';
        }

        $slug = Str::kebab($group);
        $marker = "/* myra:nav:{$slug} */";

        if (str_contains($content, $marker)) {
            return $marker;
        }

        // A group block is created immediately before the scaffolded-pages group.
        $anchor = "    {\n        // Scaffolded pages";
        $block = "    {\n"
            . "        label: '" . str_replace("'", "\\'", $group) . "',\n"
            . "        items: [\n"
            . "            {$marker}\n"
            . "        ],\n"
            . "    },\n";

        if (! str_contains($content, $anchor)) {
            $this->warn("Could not locate the scaffolded-pages nav group; falling back to the default group.");
            return '/* myra:nav */';
        }

        $content = str_replace($anchor, $block . $anchor, $content);

        return $marker;
    }

    /** Locale files the generators keep in lock-step. */
    protected const LOCALES = ['en', 'ms', 'zh'];

    /**
     * Write generated UI strings into en/ms/zh. Accepts a map of dot key =>
     * English string, or a plain list of dot keys (the last segment is
     * title-cased). Idempotent: an existing key is never overwritten.
     *
     * ms/zh receive the English string as a placeholder — a generator cannot
     * translate a model name it was handed seconds ago. Translate them before
     * shipping the resource.
     */
    protected function addTranslations(array $dotKeys): void
    {
        $entries = [];
        foreach ($dotKeys as $key => $value) {
            if (is_int($key)) {
                $key = $value;
                $value = Str::headline(Str::afterLast($key, '.'));
            }
            $entries[$key] = $value;
        }

        foreach (self::LOCALES as $locale) {
            $file = base_path("resources/js/i18n/locales/{$locale}.json");

            if (! file_exists($file)) {
                $this->warn("Locale file missing, skipping: {$locale}.json");
                continue;
            }

            $data = json_decode(file_get_contents($file), true);

            if (! is_array($data)) {
                $this->error("Locale file is not valid JSON: {$locale}.json");
                return;
            }

            foreach ($entries as $key => $value) {
                if (data_get($data, $key) === null) {
                    data_set($data, $key, $value);
                }
            }

            file_put_contents(
                $file,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
            );
        }

        $this->info('Translations: '.count($entries).' keys written to '.implode('/', self::LOCALES).'.');
        $this->assertLocaleParity();
    }

    /** Warn (and return false) when the three locale files disagree in key count. */
    protected function assertLocaleParity(): bool
    {
        $counts = [];

        foreach (self::LOCALES as $locale) {
            $file = base_path("resources/js/i18n/locales/{$locale}.json");
            if (! file_exists($file)) {
                return false;
            }
            $counts[$locale] = count($this->flattenLocale(json_decode(file_get_contents($file), true) ?: []));
        }

        if (count(array_unique($counts)) === 1) {
            return true;
        }

        $this->warn('Locale parity broken: '.json_encode($counts).' — fix before shipping.');

        return false;
    }

    /** @return array<string,mixed> dot key => leaf value */
    private function flattenLocale(array $data, string $prefix = ''): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            $dot = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $out += $this->flattenLocale($value, $dot);
                continue;
            }
            $out[$dot] = $value;
        }

        return $out;
    }

    /** Add a plugin class to config/myra.php at the myra:plugins marker (idempotent). */
    protected function registerPluginClass(string $fqcn): void
    {
        $file = base_path('config/myra.php');
        $content = file_get_contents($file);
        $literal = '\\'.ltrim($fqcn, '\\').'::class';

        if (str_contains($content, $literal)) {
            $this->warn("Plugin {$fqcn} already registered, skipping.");
            return;
        }

        if (! str_contains($content, '// myra:plugins')) {
            $this->warn("config/myra.php has no // myra:plugins marker. Add {$literal} to myra.extensions.plugins by hand.");
            return;
        }

        $content = str_replace(
            '// myra:plugins',
            "{$literal},\n            // myra:plugins",
            $content,
        );

        file_put_contents($file, $content);
        $this->info("Plugin registered in config/myra.php: {$fqcn}");
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
