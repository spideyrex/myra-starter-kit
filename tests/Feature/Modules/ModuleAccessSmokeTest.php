<?php

namespace Tests\Feature\Modules;

use Tests\TestCase;

/**
 * Smoke coverage for the remaining admin module index pages: each must load for
 * a super-admin and be forbidden for a user without the relevant permission.
 */
class ModuleAccessSmokeTest extends TestCase
{
    /** @return array<string, array{0:string}> */
    public static function moduleRoutes(): array
    {
        return [
            'media' => ['admin.media.index'],
            'email logs' => ['admin.email-logs.index'],
            'email settings' => ['admin.email-settings.index'],
            'firebase settings' => ['admin.firebase-settings.index'],
            'activity logs' => ['admin.activity-logs.index'],
            'backups' => ['admin.backups.index'],
            'system health' => ['admin.system-health.index'],
            'api tokens' => ['admin.api-tokens.index'],
            'notifications' => ['admin.notifications.index'],
            'permissions' => ['admin.permissions.index'],
            'demo' => ['admin.demo.index'],
            'demo map' => ['admin.demo.map'],
        ];
    }

    /** @dataProvider moduleRoutes */
    public function test_super_admin_can_access(string $routeName): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route($routeName))->assertSuccessful();
    }

    /** @dataProvider moduleRoutes */
    public function test_user_without_permission_is_forbidden(string $routeName): void
    {
        $this->actingAsUser();
        $this->get(route($routeName))->assertForbidden();
    }
}
