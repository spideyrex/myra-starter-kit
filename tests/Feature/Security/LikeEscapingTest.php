<?php

namespace Tests\Feature\Security;

use App\Admin\Traits\HasRelationManagers;
use App\Models\EmailLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Every LIKE predicate must escape the user's `%` and `_`, so a search for "%"
 * matches only rows containing a literal percent sign instead of the whole table.
 *
 * These run on sqlite, which has no default escape character — they only pass
 * because Sql::whereLike() emits an explicit ESCAPE clause.
 */
class LikeEscapingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The listing assertions read the Inertia page props out of the rendered
        // root view, which pulls in @vite. No build manifest exists under test.
        $this->withoutVite();
    }

    /** @return array<string,mixed> the Inertia page props for a GET */
    private function props(string $url): array
    {
        $response = $this->get($url);
        $response->assertOk();

        return $response->viewData('page')['props'];
    }

    private function csvRows(string $body): array
    {
        $lines = array_values(array_filter(explode("\n", trim(str_replace("\r\n", "\n", $body)))));

        return array_map('str_getcsv', $lines);
    }

    // --- UserService::exportQuery() ------------------------------------------

    public function test_user_export_search_treats_percent_as_a_literal(): void
    {
        $this->actingAsSuperAdmin();

        User::factory()->create(['name' => 'Literal%Match']);
        User::factory()->count(3)->create(['name' => 'Plain Name']);

        $rows = $this->csvRows(
            $this->get(route('admin.users.export-csv', ['search' => '%']))->streamedContent(),
        );

        // Header + exactly the one literal match, not every user in the table.
        $this->assertCount(2, $rows);
        $this->assertSame('Literal%Match', $rows[1][1]);
    }

    public function test_user_export_search_treats_underscore_as_a_literal(): void
    {
        $this->actingAsSuperAdmin();

        User::factory()->create(['name' => 'Under_score']);
        User::factory()->create(['name' => 'Underxscore']);

        $rows = $this->csvRows(
            $this->get(route('admin.users.export-csv', ['search' => 'Under_score']))->streamedContent(),
        );

        $this->assertCount(2, $rows);
        $this->assertSame('Under_score', $rows[1][1]);
    }

    // --- AdminNotificationController ------------------------------------------

    public function test_admin_notification_search_treats_percent_as_a_literal(): void
    {
        $user = $this->actingAsSuperAdmin();

        $this->seedNotification($user, 'Ninety%Percent');
        $this->seedNotification($user, 'Nothing special');
        $this->seedNotification($user, 'Also nothing');

        $props = $this->props(route('admin.notifications.index', ['search' => '%']));

        $this->assertCount(1, $props['notifications']['data']);
        $this->assertSame('Ninety%Percent', $props['notifications']['data'][0]['title']);
    }

    private function seedNotification(User $user, string $title): void
    {
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['type' => 'system', 'title' => $title, 'message' => $title],
            'read_at' => null,
        ]);
    }

    // --- ActivityLogController (shared by the listing AND the export) ----------

    public function test_activity_log_search_treats_percent_as_a_literal(): void
    {
        $this->actingAsSuperAdmin();

        Activity::create(['log_name' => 'default', 'properties' => [], 'description' => 'Discount 50% applied']);
        Activity::create(['log_name' => 'default', 'properties' => [], 'description' => 'Nothing to see']);
        Activity::create(['log_name' => 'default', 'properties' => [], 'description' => 'Still nothing']);

        $props = $this->props(route('admin.activity-logs.index', ['search' => '%']));

        $this->assertCount(1, $props['activities']['data']);
        $this->assertSame('Discount 50% applied', $props['activities']['data'][0]['description']);
    }

    public function test_activity_log_export_search_treats_percent_as_a_literal(): void
    {
        $this->actingAsSuperAdmin();

        Activity::create(['log_name' => 'default', 'properties' => [], 'description' => 'Discount 50% applied']);
        Activity::create(['log_name' => 'default', 'properties' => [], 'description' => 'Nothing to see']);

        $rows = $this->csvRows(
            $this->get(route('admin.activity-logs.export-csv', ['search' => '%']))->streamedContent(),
        );

        $this->assertCount(2, $rows);
        $this->assertSame('Discount 50% applied', $rows[1][1]);
    }

    // --- EmailLogController ----------------------------------------------------

    public function test_email_log_search_treats_percent_as_a_literal(): void
    {
        $this->actingAsSuperAdmin();

        EmailLog::factory()->create(['subject' => '100% off this week']);
        EmailLog::factory()->count(4)->create(['subject' => 'Regular subject', 'to' => 'a@example.com']);

        $props = $this->props(route('admin.email-logs.index', ['search' => '%']));

        $this->assertCount(1, $props['logs']['data']);
        $this->assertSame('100% off this week', $props['logs']['data'][0]['subject']);
    }

    // --- MediaController -------------------------------------------------------

    public function test_media_search_treats_percent_as_a_literal(): void
    {
        $this->actingAsSuperAdmin();

        $this->seedMedia('100%-off.png', 'image/png');
        $this->seedMedia('holiday.png', 'image/png');
        $this->seedMedia('clip.mp4', 'video/mp4');

        $props = $this->props(route('admin.media.index', ['search' => '%']));

        $this->assertCount(1, $props['media']['data']);
        $this->assertSame('100%-off.png', $props['media']['data'][0]['file_name']);
    }

    /** The prefix match is the higher-severity one: an unescaped % turns it into an arbitrary pattern. */
    public function test_media_type_prefix_filter_treats_percent_as_a_literal(): void
    {
        $this->actingAsSuperAdmin();

        $this->seedMedia('holiday.png', 'image/png');
        $this->seedMedia('clip.mp4', 'video/mp4');

        $wildcard = $this->props(route('admin.media.index', ['type' => 'image/%']));
        $this->assertCount(0, $wildcard['media']['data']);

        $literal = $this->props(route('admin.media.index', ['type' => 'image/']));
        $this->assertCount(1, $literal['media']['data']);
    }

    private function seedMedia(string $fileName, string $mimeType): void
    {
        DB::table('media')->insert([
            'model_type' => User::class,
            'model_id' => 1,
            'uuid' => (string) Str::uuid(),
            'collection_name' => 'uploads',
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 1024,
            'manipulations' => '{}',
            'custom_properties' => '{}',
            'generated_conversions' => '{}',
            'responsive_images' => '{}',
            'order_column' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- HasRelationManagers ---------------------------------------------------

    private function relationHarness(): object
    {
        return new class
        {
            use HasRelationManagers;

            public function run(
                Model $model,
                string $relation,
                Request $request,
                array $searchable = [],
                array $sortable = [],
                string $defaultSort = 'id',
            ): LengthAwarePaginator {
                return $this->paginateRelation($model, $relation, $request, $searchable, 10, '', $sortable, $defaultSort);
            }
        };
    }

    private function userWithRoles(): User
    {
        $user = $this->makeUser();

        foreach (['discount%50', 'plain-role', 'other-role'] as $name) {
            $user->assignRole(Role::create(['name' => $name, 'guard_name' => 'web']));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_relation_search_treats_percent_as_a_literal(): void
    {
        $user = $this->userWithRoles();

        $page = $this->relationHarness()->run(
            $user,
            'roles',
            Request::create('/', 'GET', ['search' => '%']),
            ['name'],
        );

        $this->assertSame(1, $page->total());
        $this->assertSame('discount%50', $page->items()[0]->name);
    }

    public function test_an_unlisted_sort_column_falls_back_instead_of_reaching_order_by(): void
    {
        $user = $this->userWithRoles();

        $page = $this->relationHarness()->run(
            $user,
            'roles',
            Request::create('/', 'GET', ['sort' => 'id); drop table users;--', 'direction' => 'DESC--']),
            ['name'],
        );

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertSame(3, $page->total());

        // 'DESC--' is not 'asc', so it normalises to a descending id sort.
        $ids = array_map(fn ($r) => $r->id, $page->items());
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids);
    }

    public function test_a_whitelisted_sort_column_is_honoured(): void
    {
        $user = $this->userWithRoles();

        $page = $this->relationHarness()->run(
            $user,
            'roles',
            Request::create('/', 'GET', ['sort' => 'name', 'direction' => 'asc']),
            ['name'],
        );

        $names = array_map(fn ($r) => $r->name, $page->items());
        $sorted = $names;
        sort($sorted);
        $this->assertSame($sorted, $names);
    }
}
