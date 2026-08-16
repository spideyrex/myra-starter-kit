<?php

namespace App\Admin\Tenancy\Middleware;

use App\Admin\Tenancy\Tenancy;
use Closure;

/** Job middleware: runs the job inside the tenant captured at dispatch. */
final class BindsTenant
{
    public function handle(object $job, Closure $next): mixed
    {
        $tenantId = property_exists($job, 'myraTenantId') ? $job->myraTenantId : null;

        // Nothing captured (a console/scheduler dispatch) means "no opinion":
        // binding null here would pin the job to no-tenant and, because the
        // scope fails closed, silently starve a job that sets its own actor.
        if ($tenantId === null) {
            return $next($job);
        }

        return Tenancy::for($tenantId, fn () => $next($job));
    }
}
