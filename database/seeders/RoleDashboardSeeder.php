<?php

namespace Database\Seeders;

use App\Admin\Dashboard\DashboardKey;
use App\Admin\Dashboard\LayoutShape;
use App\Models\Role;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Starter role dashboards.
 *
 * DELIBERATELY NOT registered in DatabaseSeeder. Run it explicitly with
 * `php artisan myra:role-dashboards:seed`. An upgrade must never silently
 * rearrange a live deployment's dashboards.
 *
 * Starters are ENTRIES-ONLY because config('myra.dashboard.catalogue') ships
 * empty — an instance-based starter would resolve to nothing on a stock
 * install. An entries-only payload is a reordering and cannot leak anything.
 *
 * `hidden` here is TIDYING, NOT ACCESS CONTROL. Every widget named below is
 * something that role could already see; nothing here grants or resurrects
 * anything, and every widget is still re-filtered for the VIEWING user on
 * every render.
 */
class RoleDashboardSeeder extends Seeder
{
    /**
     * Visible entries first, in order, then the tidied-away ones.
     *
     * @var array<string,array<int,array{key:string,colSpan?:array<string,int>,hidden?:bool}>>
     */
    private const STARTERS = [
        'super-admin' => [
            ['key' => 'total_users'],
            ['key' => 'active_users'],
            ['key' => 'new_users'],
            ['key' => 'pending_verifications'],
            ['key' => 'user_growth'],
            ['key' => 'users_by_status'],
        ],
        // The queue you act on comes first.
        'admin' => [
            ['key' => 'pending_verifications'],
            ['key' => 'total_users'],
            ['key' => 'active_users'],
            ['key' => 'new_users'],
            ['key' => 'user_growth'],
            ['key' => 'users_by_status'],
        ],
        'manager' => [
            ['key' => 'user_growth', 'colSpan' => ['sm' => 2, 'lg' => 4]],
            ['key' => 'active_users'],
            ['key' => 'total_users'],
            ['key' => 'new_users'],
            ['key' => 'users_by_status'],
            ['key' => 'pending_verifications', 'hidden' => true],
        ],
        'editor' => [
            ['key' => 'total_users'],
            ['key' => 'new_users'],
            ['key' => 'user_growth'],
            ['key' => 'active_users'],
            ['key' => 'pending_verifications', 'hidden' => true],
            ['key' => 'users_by_status', 'hidden' => true],
        ],
        'viewer' => [
            ['key' => 'total_users'],
            ['key' => 'active_users'],
            ['key' => 'user_growth', 'colSpan' => ['sm' => 2, 'lg' => 2]],
            ['key' => 'new_users', 'hidden' => true],
            ['key' => 'pending_verifications', 'hidden' => true],
            ['key' => 'users_by_status', 'hidden' => true],
        ],
    ];

    /** @var array<int,array{role:string,status:string,widgets:int}> */
    public array $summary = [];

    public function run(): void
    {
        $this->summary = [];

        $model = $this->rowModel();
        $key = DashboardKey::MAIN;

        foreach (self::STARTERS as $name => $entries) {
            $role = Role::query()->where('name', $name)->first();

            if ($role === null) {
                $this->summary[] = ['role' => $name, 'status' => 'missing', 'widgets' => 0];

                continue;
            }

            $payload = $this->payloadFor($role, $entries);

            $existing = $model::query()
                ->where('role_id', $role->getKey())
                ->where('dashboard_key', $key)
                ->exists();

            if ($existing) {
                $this->summary[] = ['role' => $name, 'status' => 'kept', 'widgets' => count($payload['entries'])];

                continue;
            }

            $model::query()->create([
                'role_id' => $role->getKey(),
                'dashboard_key' => $key,
                'payload' => $payload,
            ]);

            $this->summary[] = ['role' => $name, 'status' => 'created', 'widgets' => count($payload['entries'])];
        }

        $this->command?->info('Role dashboards seeded. Existing rows were left untouched.');
    }

    /** @return array<string,array<int,array>> */
    public static function starters(): array
    {
        return self::STARTERS;
    }

    /**
     * Shape-checked AND resolved against the role's own permission set before
     * it is ever written. A starter naming something the role cannot see is a
     * loud failure, never a silently dropped entry.
     *
     * @param  array<int,array>  $entries
     * @return array{version:int,entries:array<int,array>,instances:array<int,array>}
     */
    private function payloadFor(Role $role, array $entries): array
    {
        $ordered = [];

        foreach (array_values($entries) as $index => $entry) {
            $ordered[] = $entry + ['order' => $index];
        }

        $payload = LayoutShape::assert(
            ['version' => 1, 'entries' => $ordered, 'instances' => []],
            'payload',
        );

        $principal = new \App\Admin\Dashboard\RolePrincipal($role);

        $resolved = \App\Admin\Dashboard\WidgetInstance::resolveAll($payload['instances'], $principal);

        if (count($resolved) !== count($payload['instances'])) {
            throw new RuntimeException("Starter dashboard for role [{$role->name}] declares an instance that role cannot resolve.");
        }

        $allowed = \App\Admin\Dashboard\StaticWidgetRegistry::visibleTo($principal);

        foreach ($payload['entries'] as $entry) {
            if (! in_array($entry['key'], $allowed, true)) {
                throw new RuntimeException("Starter dashboard for role [{$role->name}] names widget [{$entry['key']}], which that role cannot see.");
            }
        }

        return $payload;
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function rowModel(): string
    {
        $model = \App\Models\RoleDashboard::class;

        if (! class_exists($model)) {
            throw new RuntimeException('App\Models\RoleDashboard is missing — run the role-dashboard migrations first.');
        }

        return $model;
    }
}
