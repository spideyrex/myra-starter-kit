<?php

namespace App\Console\Commands\Myra\Concerns;

use Illuminate\Support\Arr;

/**
 * Navigation-side scaffolding for make:myra-cluster / -nested / -singleton.
 *
 * Deliberately separate from ScaffoldsAdmin: the cluster generators must be able
 * to evolve without touching the resource generators.
 */
trait ScaffoldsNavigation
{
    /** Add a cluster class to config/myra.php at the myra:clusters marker (idempotent). */
    protected function registerCluster(string $fqcn): void
    {
        $file = base_path('config/myra.php');

        if (! file_exists($file)) {
            return;
        }

        $content = file_get_contents($file);
        $entry = '\\'.ltrim($fqcn, '\\').'::class';

        if (str_contains($content, $entry)) {
            $this->warn("Cluster {$fqcn} already registered, skipping.");

            return;
        }

        if (! str_contains($content, '// myra:clusters')) {
            $this->warn('No myra:clusters marker in config/myra.php — add the cluster manually.');

            return;
        }

        $content = str_replace(
            '// myra:clusters',
            "{$entry},\n        // myra:clusters",
            $content,
        );

        file_put_contents($file, $content);
        $this->info("Cluster registered: {$fqcn}");
    }

    /**
     * Write dot-notated keys into en/ms/zh. Existing keys are never overwritten.
     * ms/zh receive the English default as a placeholder — the command says so,
     * because a silently untranslated string is worse than a loud one.
     *
     * @param  array<string,string>  $dotKeys  key => English default
     */
    protected function writeNavLocaleKeys(array $dotKeys): void
    {
        $written = 0;

        foreach (['en', 'ms', 'zh'] as $locale) {
            $file = base_path("resources/js/i18n/locales/{$locale}.json");

            if (! file_exists($file)) {
                continue;
            }

            $messages = json_decode(file_get_contents($file), true);

            if (! is_array($messages)) {
                $this->error("Could not parse {$locale}.json — locale keys not written.");

                return;
            }

            foreach ($dotKeys as $key => $default) {
                if (Arr::has($messages, $key)) {
                    continue;
                }
                Arr::set($messages, $key, $default);
                $written++;
            }

            file_put_contents(
                $file,
                json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
            );
        }

        if ($written > 0) {
            $this->info('Locale keys written to en/ms/zh — translate the ms and zh copies.');
        }

        $this->assertNavLocaleParity();
    }

    /** Warn (never fail) when the three locale files disagree on key count. */
    protected function assertNavLocaleParity(): bool
    {
        $counts = [];

        foreach (['en', 'ms', 'zh'] as $locale) {
            $file = base_path("resources/js/i18n/locales/{$locale}.json");
            $messages = file_exists($file) ? json_decode(file_get_contents($file), true) : null;
            $counts[$locale] = is_array($messages) ? count(Arr::dot($messages)) : -1;
        }

        if (count(array_unique($counts)) === 1) {
            return true;
        }

        $this->warn('Locale key counts differ: '.json_encode($counts));

        return false;
    }
}
