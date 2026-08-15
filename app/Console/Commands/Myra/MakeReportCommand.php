<?php

namespace App\Console\Commands\Myra;

use App\Console\Commands\Myra\Concerns\ScaffoldsAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeReportCommand extends Command
{
    use ScaffoldsAdmin;

    protected $signature = 'make:myra-report {name : Model name in PascalCase (e.g. Order)}
        {--key= : Registry key for the report (default: kebab-plural of the model)}
        {--print : Print the config snippet instead of editing config/myra.php}';

    protected $description = 'Scaffold a ReportDefinition (dimensions + measures, all aggregation in SQL) and register it in config(myra.reports.definitions)';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $key = (string) ($this->option('key') ?: Str::kebab(Str::plural($name)));
        $print = (bool) $this->option('print');

        $this->writeRaw("app/Admin/Report/Reports/{$name}Report.php", $this->definition($name, $key));

        $line = "        '{$key}' => \\App\\Admin\\Report\\Reports\\{$name}Report::class,";

        if ($print) {
            $this->newLine();
            $this->components->info('Add to config/myra.php under reports.definitions:');
            $this->line($line);
        } else {
            $this->registerDefinition($key, $line);
        }

        $this->newLine();
        $this->components->info("Report '{$key}' scaffolded → /admin/reports/{$key}");
        $this->components->bulletList([
            'Every dimension that is not a date MUST declare limit() — an unbounded GROUP BY is a mis-declaration.',
            'Measures are computed by the database. Never post-process rows in PHP.',
            'filters() should reuse the SAME FieldSet your index route already whitelists.',
        ]);

        return self::SUCCESS;
    }

    private function registerDefinition(string $key, string $line): void
    {
        $path = base_path('config/myra.php');
        $config = (string) file_get_contents($path);

        if (str_contains($config, "'{$key}' => \\App\\Admin\\Report\\Reports\\")) {
            $this->components->warn("config/myra.php already registers '{$key}'.");

            return;
        }

        $anchor = "        // myra:reports — make:myra-report inserts definitions above this line.";

        if (! str_contains($config, $anchor)) {
            $this->components->warn('Anchor not found in config/myra.php; add the definition manually:');
            $this->line($line);

            return;
        }

        file_put_contents($path, str_replace($anchor, $line . "\n" . $anchor, $config));
        $this->components->info('config/myra.php updated.');
    }

    private function definition(string $name, string $key): string
    {
        $title = Str::headline($name);

        return <<<PHP
<?php

namespace App\\Admin\\Report\\Reports;

use App\\Admin\\QueryBuilder\\FieldSet;
use App\\Admin\\QueryBuilder\\FieldSpec;
use App\\Admin\\Report\\Bucket;
use App\\Admin\\Report\\ComparisonPeriod;
use App\\Admin\\Report\\Dimension;
use App\\Admin\\Report\\Measure;
use App\\Admin\\Report\\PeriodPreset;
use App\\Admin\\Report\\ReportDefinition;
use App\\Models\\{$name};
use Illuminate\\Contracts\\Auth\\Authenticatable;
use Illuminate\\Database\\Eloquent\\Builder;

final class {$name}Report
{
    public static function definition(): ReportDefinition
    {
        return ReportDefinition::make('{$key}')
            ->model({$name}::class)
            ->titleKey('reports.{$key}.title')
            ->permission('reports.view')
            // Ownership scope is applied FIRST, before any client input.
            ->scope(fn (Builder \$q, ?Authenticatable \$actor) => \$q->when(
                ! (\$actor?->hasRole(config('shield.super_admin_role', 'super-admin')) ?? false),
                fn (Builder \$q) => \$q->where('created_by', \$actor?->getAuthIdentifier()),
            ))
            ->dateColumn('created_at')
            ->dimensions([
                Dimension::date('created_at')->labelKey('reports.dim.createdAt')
                    ->bucket(Bucket::Day)
                    ->allowedBuckets([Bucket::Day, Bucket::Week, Bucket::Month]),
                // Categorical dimensions MUST declare limit().
                Dimension::field('status')->labelKey('reports.dim.status')->limit(10)->other(),
            ])
            ->measures([
                Measure::count('total')->labelKey('reports.measure.total'),
            ])
            ->filters(FieldSet::make([
                FieldSpec::date('created_at')->labelKey('filters.field.createdAt'),
            ]))
            ->defaults('created_at', ['total'], PeriodPreset::Last30Days)
            ->comparisons([ComparisonPeriod::Previous])
            ->maxGroups((int) config('myra.reports.max_groups', 200))
            ->formats(['csv', 'xlsx'])
            ->schedulable();
    }
}

PHP;
    }
}
