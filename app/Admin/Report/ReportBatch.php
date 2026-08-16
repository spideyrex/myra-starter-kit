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
     * @param  array<string, string>  $knownVersions  slot => the version the client already holds
     * @return array<string, array>
     *
     * @throws ReportException when more than MAX_REPORTS are requested
     */
    public static function run(array $specs, ?Authenticatable $user, array $knownVersions = []): array
    {
        $max = (int) config('myra.reports.max_batch', self::MAX_REPORTS);

        if (count($specs) > $max) {
            throw ReportException::make('reports.errors.tooManyReports');
        }

        $out = [];

        // >>> MYRA v2.5 [B] START
        /** @var array<string,string> one stamp query per report, not per slot */
        $datasets = [];
        // <<< MYRA v2.5 [B] END

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

            // >>> MYRA v2.5 [B] START
            // Freshness FIRST: an unchanged slot skips the aggregation entirely.
            // `''` (no versionKey declared, or the stamp query failed) can never
            // match, so the default path is byte-identical to v2.4.0.
            $version = '';

            if (ReportResult::stampable($request)) {
                $datasets[$key] ??= ReportResult::datasetStamp($definition, $user);
                $version = ReportResult::stamp($definition, $request, $user, $datasets[$key]);
            }

            $known = $knownVersions[(string) $slot] ?? null;

            if ($version !== '' && is_string($known) && $known === $version) {
                $out[(string) $slot] = ['unchanged' => true, 'version' => $version];

                continue;
            }
            // <<< MYRA v2.5 [B] END

            $runner = new ReportRunner($definition);

            $payload = $request->mode() === 'stat'
                ? $runner->stat($request, $user)->toArray()
                : $runner->run($request, $user)->toArray();

            // >>> MYRA v2.5 [B] START
            $payload['version'] = $version;
            // <<< MYRA v2.5 [B] END

            $out[(string) $slot] = $payload;
        }

        return $out;
    }
}
