<?php

namespace App\Admin\Report;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/** A dashboard with nine widgets makes ONE request, not nine. */
final class ReportBatch
{
    public const MAX_REPORTS = 12;

    /**
     * @param  array<string, array<string,mixed>>  $specs  widgetKey => raw state (carrying `report`)
     * @return array<string, array>
     *
     * @throws ReportException when more than MAX_REPORTS are requested
     */
    public static function run(array $specs, ?Authenticatable $user): array
    {
        $max = (int) config('myra.reports.max_batch', self::MAX_REPORTS);

        if (count($specs) > $max) {
            throw ReportException::make('reports.errors.tooManyReports');
        }

        $out = [];

        foreach ($specs as $slot => $spec) {
            $spec = is_array($spec) ? $spec : [];
            $key = (string) ($spec['report'] ?? $slot);

            if (! ReportRegistry::has($key)) {
                continue;
            }

            $definition = ReportRegistry::resolve($key);

            if (! ($user instanceof Authorizable && $user->can($definition->permissionAbility()))) {
                continue;
            }

            $request = ReportRequest::parse($spec, $definition, $user);
            $runner = new ReportRunner($definition);

            $out[(string) $slot] = $request->mode() === 'stat'
                ? $runner->stat($request, $user)->toArray()
                : $runner->run($request, $user)->toArray();
        }

        return $out;
    }
}
