<?php

namespace App\Admin\Dashboard;

use App\Models\DashboardLayout;
use Illuminate\Contracts\Auth\Authenticatable;

final class LayoutResolver
{
    /**
     * `instances` are re-derived server-side on every load. A corrupt row must
     * never white-screen the dashboard, so any throw degrades to null.
     *
     * @return array{version:int,entries:array,instances:array}|null
     */
    public static function forInertia(?DashboardLayout $row, ?Authenticatable $user): ?array
    {
        return self::fromPayload($row?->payload, $user);
    }

    // >>> MYRA v2.7 [A] START
    /**
     * THE single filter for BOTH tables. A parallel path for role-authored rows
     * is exactly where the leak would live, and is forbidden.
     *
     * $user is ALWAYS the VIEWING user, never the row's author. That one fact is
     * what makes an admin-authored role dashboard safe to render for someone
     * else: instances are re-derived through WidgetInstance (five fail-closed
     * gates), and entries are narrowed to keys the viewer may actually see.
     *
     * @return array{version:int,entries:array,instances:array}|null
     */
    public static function fromPayload(?array $payload, ?Authenticatable $user): ?array
    {
        if ($payload === null) {
            return null;
        }

        try {
            $shape = LayoutShape::filter($payload);
            $instances = WidgetInstance::resolveAll($shape['instances'], $user);

            $allowed = array_merge(
                array_column($instances, 'key'),
                StaticWidgetRegistry::visibleTo($user),
            );

            $entries = array_values(array_filter(
                $shape['entries'],
                static fn (array $e) => in_array($e['key'], $allowed, true),
            ));

            foreach ($entries as $i => $entry) {
                $entries[$i]['order'] = $i;
            }

            return [
                'version' => 1,
                'entries' => $entries,
                'instances' => $instances,
            ];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
    // <<< MYRA v2.7 [A] END
}
