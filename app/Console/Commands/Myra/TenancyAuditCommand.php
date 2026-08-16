<?php

namespace App\Console\Commands\Myra;

use App\Admin\Tenancy\Tenancy;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only readiness check for turning tenancy on. Never writes.
 *
 * A legacy row with a NULL tenant column is invisible to non-super-admins under
 * `null_rows = strict`, so this command refuses readiness while any remain: a
 * blocked deploy beats a silent visibility change.
 */
class TenancyAuditCommand extends Command
{
    protected $signature = 'myra:tenancy-audit';

    protected $description = 'Check whether every tenant-scoped model is ready for myra.tenancy.enabled';

    public function handle(): int
    {
        $column = Tenancy::column();
        $strict = config('myra.tenancy.null_rows', 'strict') === 'strict';
        $models = array_values(array_filter((array) config('myra.tenancy.models', []), 'is_string'));

        $this->newLine();
        $this->line('  <fg=magenta;options=bold>Myra tenancy audit</>');
        $this->components->twoColumnDetail('enabled', Tenancy::enabled() ? '<fg=yellow>yes</>' : '<fg=green>no</>');
        $this->components->twoColumnDetail('column', $column);
        $this->components->twoColumnDetail('null rows', $strict ? 'strict' : 'shared');
        $this->newLine();

        if ($models === []) {
            $this->components->warn('myra.tenancy.models is empty — nothing is tenant-scoped.');
        }

        $ready = true;
        $scopedTables = [];

        foreach ($models as $class) {
            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                $this->components->error("{$class}: not an Eloquent model.");
                $ready = false;

                continue;
            }

            /** @var Model $instance */
            $instance = new $class;
            $table = $instance->getTable();
            $scopedTables[] = $table;

            $usesTrait = in_array(BelongsToTenant::class, class_uses_recursive($class), true);
            $hasColumn = Schema::hasColumn($table, $column);

            $this->line("  <options=bold>{$class}</> <fg=gray>({$table})</>");
            $ready = $this->check('uses BelongsToTenant', $usesTrait) && $ready;
            $ready = $this->check("has {$column}", $hasColumn) && $ready;

            if (! $hasColumn) {
                $this->newLine();

                continue;
            }

            $nulls = (int) DB::table($table)->whereNull($column)->count();
            $ready = $this->check(
                "{$nulls} row(s) with a null {$column}",
                $nulls === 0 || ! $strict,
                $strict ? 'invisible to non-super-admins once enabled' : 'visible to every tenant (shared)',
            ) && $ready;

            // Performance, not correctness: a warning, never a blocked deploy.
            if (! $this->hasLeadingIndex($table, $column)) {
                $this->components->warn(
                    "{$table}: no index leads on {$column} — every tenant-filtered listing is a full scan.",
                );
            }

            $this->newLine();
        }

        $this->reportUnlisted($column, $scopedTables);

        $this->newLine();

        if (! $ready) {
            $this->components->error('NOT READY — resolve the items above before enabling tenancy.');

            return self::FAILURE;
        }

        $this->components->info('READY — every listed model is scoped and free of null tenant rows.');

        return self::SUCCESS;
    }

    /** Tables carrying the tenant column whose model is not listed — the inverse mistake. */
    private function reportUnlisted(string $column, array $scopedTables): void
    {
        $unlisted = [];

        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;

            if (in_array($table, $scopedTables, true)) {
                continue;
            }

            if (Schema::hasColumn($table, $column)) {
                $unlisted[] = $table;
            }
        }

        if ($unlisted !== []) {
            $this->components->warn(
                'Tables with a '.$column.' column but no listed model: '.implode(', ', $unlisted)
                .'. These are scoped only where a query taps Tenancy::apply().',
            );
        }
    }

    private function check(string $label, bool $ok, ?string $why = null): bool
    {
        $this->components->twoColumnDetail(
            '  '.$label,
            $ok ? '<fg=green>ok</>' : '<fg=red>FAIL</>'.($why ? " <fg=gray>({$why})</>" : ''),
        );

        return $ok;
    }

    private function hasLeadingIndex(string $table, string $column): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $index) {
                if (($index['columns'][0] ?? null) === $column) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }
}
