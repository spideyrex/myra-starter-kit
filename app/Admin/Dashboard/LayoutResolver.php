<?php

namespace App\Admin\Dashboard;

use App\Models\DashboardLayout;
use Illuminate\Contracts\Auth\Authenticatable;

final class LayoutResolver
{
    /**
     * `entries` are inert presentation deltas and travel verbatim; `instances`
     * are re-derived server-side on every load. A corrupt row must never
     * white-screen the dashboard, so any throw degrades to null.
     *
     * @return array{version:int,entries:array,instances:array}|null
     */
    public static function forInertia(?DashboardLayout $row, ?Authenticatable $user): ?array
    {
        if ($row === null) {
            return null;
        }

        try {
            $payload = is_array($row->payload) ? $row->payload : [];
            $entries = $payload['entries'] ?? [];
            $instances = $payload['instances'] ?? [];

            return [
                'version' => 1,
                'entries' => is_array($entries) && array_is_list($entries) ? $entries : [],
                'instances' => WidgetInstance::resolveAll(
                    is_array($instances) && array_is_list($instances) ? $instances : [],
                    $user,
                ),
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
