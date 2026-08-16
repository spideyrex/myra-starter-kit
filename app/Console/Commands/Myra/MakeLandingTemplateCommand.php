<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use App\Console\Commands\Myra\Concerns\ScaffoldsNavigation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeLandingTemplateCommand extends Command
{
    use ScaffoldsAdmin;
    use ScaffoldsNavigation;

    protected $signature = 'make:myra-landing {name : Template name in PascalCase (e.g. Gallery)}
        {--print : Print the config snippet instead of editing config/myra.php}';

    protected $description = 'Scaffold a public landing-page template (Vue page + registry class + en/ms/zh keys) and register it in config/myra.php';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $key = Str::kebab($name);

        $this->writeRaw("app/Homepage/Templates/{$name}Template.php", $this->registryClass($name, $key));
        $this->writeRaw("resources/js/Pages/Public/Templates/{$name}.vue", $this->page());

        $this->writeNavLocaleKeys([
            "landing.templates.{$key}.title" => $name,
            "landing.templates.{$key}.description" => "The {$name} landing-page template.",
        ]);

        $entry = "\\App\\Homepage\\Templates\\{$name}Template::class";

        if ($this->option('print')) {
            $this->newLine();
            $this->raw("            {$entry},");
        } else {
            $this->registerTemplate($entry);
        }

        $this->newLine();
        $this->components->info("Landing template '{$key}' scaffolded.");
        $this->components->warn('Add a thumbnail at public/images/templates/'.$key.'.svg, then run `npm run build`.');

        return self::SUCCESS;
    }

    /** Insert the class at the myra:landing marker (idempotent). */
    private function registerTemplate(string $entry): void
    {
        $file = base_path('config/myra.php');
        $content = file_exists($file) ? file_get_contents($file) : '';

        if ($content === '' || ! str_contains($content, '// myra:landing')) {
            $this->warn('No myra:landing marker in config/myra.php — register the template manually.');

            return;
        }

        if (str_contains($content, $entry)) {
            $this->warn('Template already registered, skipping.');

            return;
        }

        file_put_contents($file, str_replace(
            '// myra:landing',
            "{$entry},\n            // myra:landing",
            $content,
        ));

        $this->info('Template registered in config/myra.php');
    }

    private function registryClass(string $name, string $key): string
    {
        return <<<PHP
<?php

namespace App\\Homepage\\Templates;

use App\\Homepage\\HomepageTemplate;

final class {$name}Template
{
    public static function define(): HomepageTemplate
    {
        return HomepageTemplate::make('{$key}')
            ->component('Public/Templates/{$name}')
            ->thumbnail('/images/templates/{$key}.svg')
            ->supports('hero', 'features', 'testimonials', 'pricing', 'cta')
            ->since('2.6.0');
    }
}

PHP;
    }

    private function page(): string
    {
        return <<<'VUE'
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { useThemeColors } from '@/composables/useThemeColors';
import SiteNavbar from './_shared/SiteNavbar.vue';
import SiteFooter from './_shared/SiteFooter.vue';
import OrderedSections from './_shared/OrderedSections.vue';
import { useSiteBrand } from './_shared/useSiteBrand';
import type { HomepageData } from '@/types';

withDefaults(
    defineProps<{
        settings: HomepageData;
        authenticated: boolean;
        sectionOrder?: string[];
        templateOptions?: Record<string, Record<string, unknown>>;
    }>(),
    { sectionOrder: () => [], templateOptions: () => ({}) },
);

useThemeColors();

const { name } = useSiteBrand();
</script>

<template>
    <Head :title="name" />

    <div class="min-h-screen bg-background text-foreground">
        <SiteNavbar :settings="settings" :authenticated="authenticated" />

        <main id="content">
            <OrderedSections
                :settings="settings"
                :order="sectionOrder"
                :overrides="templateOptions"
                :supports="['hero', 'features', 'testimonials', 'pricing', 'cta']"
            />
        </main>

        <SiteFooter :settings="settings" />
    </div>
</template>

VUE;
    }
}
