<?php

namespace Tests\Feature\Generators;

use App\Homepage\TemplateRegistry;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * REAL GENERATOR OUTPUT for make:myra-landing.
 *
 * The registry key and the Vue filename are two halves of one contract:
 * Home.vue keys its dispatcher on the lower-cased .vue basename, so a template
 * whose key is derived any other way is registered, validated and picked — and
 * then silently renders Classic. --print is a true dry run, so these run
 * against what the generator actually emits without touching the repository.
 */
class LandingTemplateGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
    }

    /** @return array<string,string> section => content */
    private function generate(string $name): array
    {
        Artisan::call('make:myra-landing', ['name' => $name, '--print' => true]);

        $output = str_replace("\r\n", "\n", Artisan::output());
        $parts = preg_split('/^===== (.+?) =====$/m', $output, -1, PREG_SPLIT_DELIM_CAPTURE);

        $sections = [];
        for ($i = 1; $i < count($parts); $i += 2) {
            $sections[trim($parts[$i])] = $parts[$i + 1] ?? '';
        }

        return $sections;
    }

    private function section(array $sections, string $needle): string
    {
        foreach ($sections as $name => $content) {
            if (str_contains($name, $needle)) {
                return $content;
            }
        }

        $this->fail("Generator produced no section matching [{$needle}]. Got: ".implode(', ', array_keys($sections)));
    }

    public function test_print_is_a_true_dry_run(): void
    {
        $before = file_get_contents(base_path('config/myra.php'));
        $locales = array_map(
            fn (string $code) => file_get_contents(base_path("resources/js/i18n/locales/{$code}.json")),
            array_combine(['en', 'ms', 'zh'], ['en', 'ms', 'zh']),
        );

        $this->generate('SplitHero');

        $this->assertFileDoesNotExist(app_path('Homepage/Templates/SplitHeroTemplate.php'));
        $this->assertFileDoesNotExist(resource_path('js/Pages/Public/Templates/SplitHero.vue'));
        $this->assertSame($before, file_get_contents(base_path('config/myra.php')));

        foreach ($locales as $code => $content) {
            $this->assertSame($content, file_get_contents(base_path("resources/js/i18n/locales/{$code}.json")));
        }
    }

    public function test_a_multi_word_name_keys_the_template_the_way_the_client_resolves_it(): void
    {
        $sections = $this->generate('SplitHero');
        $class = $this->section($sections, 'SplitHeroTemplate.php');

        $this->assertStringContainsString("HomepageTemplate::make('splithero')", $class);
        $this->assertStringContainsString("->component('Public/Templates/SplitHero')", $class);
        $this->assertStringContainsString("->thumbnail('/images/templates/splithero.svg')", $class);

        // kebab-case would produce 'split-hero', which the dispatcher can never match.
        $this->assertStringNotContainsString('split-hero', $class);
    }

    public function test_the_generated_page_is_named_so_the_dispatcher_resolves_the_key(): void
    {
        $sections = $this->generate('SplitHero');
        $page = $this->section($sections, 'SplitHero.vue');

        $this->assertStringContainsString('./_shared/SiteNavbar.vue', $page);
        $this->assertStringContainsString('./_shared/SiteFooter.vue', $page);
        $this->assertStringContainsString('id="content"', $page);

        // Home.vue: basename(path).replace(/\.vue$/, '').toLowerCase()
        $this->assertSame('splithero', strtolower(basename('SplitHero.vue', '.vue')));
    }

    public function test_the_scaffolded_locale_keys_use_the_same_key(): void
    {
        $keys = $this->section($this->generate('SplitHero'), 'i18n keys');

        $this->assertStringContainsString('landing.templates.splithero.title = SplitHero', $keys);
        $this->assertStringContainsString('landing.templates.splithero.description', $keys);
    }

    public function test_every_shipped_template_obeys_the_same_derivation(): void
    {
        foreach (TemplateRegistry::all() as $template) {
            $this->assertSame(
                strtolower(basename($template->componentValue())),
                $template->key(),
                "Template [{$template->key()}] names component [{$template->componentValue()}]. "
                .'Home.vue keys the dispatcher on the lower-cased filename, so these must agree.',
            );
        }
    }
}
