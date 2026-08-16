<?php

namespace App\Admin\Tenancy\Concerns;

use App\Admin\Tenancy\Middleware\BindsTenant;
use App\Admin\Tenancy\Tenancy;

/**
 * Carries the dispatching request's tenant onto the queue. A worker has no
 * session, so without this a tenant-scoped job would resolve no tenant and —
 * because the scope fails closed — quietly do nothing.
 */
trait TenantAware
{
    public int|string|null $myraTenantId = null;

    /** Call from the job's constructor. */
    protected function captureTenant(): void
    {
        $this->myraTenantId = Tenancy::enabled() ? Tenancy::id() : null;
    }

    /** @return array<int,object> */
    public function middleware(): array
    {
        return [new BindsTenant];
    }
}
