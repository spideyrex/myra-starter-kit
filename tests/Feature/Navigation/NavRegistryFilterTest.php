<?php

namespace Tests\Feature\Navigation;

use App\Admin\Clusters\LearningCluster;
use App\Admin\Navigation\NavRegistry;
use Tests\TestCase;

class NavRegistryFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        NavRegistry::flush();
        config(['myra.clusters' => [LearningCluster::class]]);
    }

    protected function tearDown(): void
    {
        NavRegistry::flush();
        parent::tearDown();
    }

    public function test_a_user_without_the_ability_sees_nothing(): void
    {
        $user = $this->actingAsUser();

        $this->assertSame([], NavRegistry::forUser($user));
    }

    public function test_a_guest_sees_nothing(): void
    {
        $this->assertSame([], NavRegistry::forUser(null));
    }

    public function test_a_permitted_user_sees_the_cluster_and_its_children(): void
    {
        $user = $this->actingAsUser();
        $user->givePermissionTo('demo.view');

        $groups = NavRegistry::forUser($user->fresh());

        $this->assertCount(1, $groups);
        $this->assertSame('navGroups.demo', $groups[0]['labelKey']);

        $cluster = $groups[0]['items'][0];
        $this->assertSame('clusters.learning.label', $cluster['labelKey']);
        $this->assertNull($cluster['href']);
        $this->assertSame('/admin/learning', $cluster['activePrefix']);

        $this->assertSame(
            ['clusters.learning.courses.label', 'clusters.learning.siteIdentity.label'],
            array_column($cluster['items'], 'labelKey'),
        );
        $this->assertSame(
            ['/admin/learning/courses', '/admin/learning/site-identity'],
            array_column($cluster['items'], 'href'),
        );
    }

    public function test_super_admin_sees_everything(): void
    {
        $user = $this->actingAsSuperAdmin();

        $groups = NavRegistry::forUser($user);

        $this->assertCount(1, $groups);
        $this->assertCount(2, $groups[0]['items'][0]['items']);
    }

    public function test_a_cluster_collapses_when_every_child_is_denied(): void
    {
        config(['myra.clusters' => [PartialCluster::class]]);

        $user = $this->actingAsUser();

        // Cluster permission is null, so only the children decide — and with no
        // child surviving there must be no empty expander left behind.
        $this->assertSame([], NavRegistry::forUser($user));
    }

    public function test_a_cluster_keeps_only_the_permitted_children(): void
    {
        config(['myra.clusters' => [PartialCluster::class]]);

        $user = $this->actingAsUser();
        $user->givePermissionTo('demo.view');

        $groups = NavRegistry::forUser($user->fresh());

        $this->assertCount(1, $groups);
        $this->assertSame(
            ['clusters.partial.allowed.label'],
            array_column($groups[0]['items'][0]['items'], 'labelKey'),
        );
    }

    public function test_add_raw_drops_an_item_with_no_label_key(): void
    {
        config(['myra.clusters' => []]);

        NavRegistry::addRaw('navGroups.demo', ['href' => '/admin/nope'], 'plugin');

        $this->assertSame([], NavRegistry::forUser($this->actingAsSuperAdmin()));
    }

    public function test_add_raw_accepts_a_plain_array_from_another_bundle(): void
    {
        config(['myra.clusters' => []]);

        NavRegistry::addRaw('navGroups.demo', [
            'labelKey' => 'plugins.blog.posts',
            'href' => '/admin/blog/posts',
            'icon' => 'Newspaper',
            'permission' => null,
            'sort' => 5,
            'unknownKey' => 'ignored',
        ], 'plugin');

        $groups = NavRegistry::forUser($this->actingAsSuperAdmin());

        $this->assertCount(1, $groups);
        $this->assertSame('plugins.blog.posts', $groups[0]['items'][0]['labelKey']);
        $this->assertArrayNotHasKey('unknownKey', $groups[0]['items'][0]);
    }

    public function test_items_are_ordered_by_sort_then_registration(): void
    {
        config(['myra.clusters' => []]);

        NavRegistry::addRaw('navGroups.demo', ['labelKey' => 'a', 'href' => '/a', 'sort' => 10]);
        NavRegistry::addRaw('navGroups.demo', ['labelKey' => 'b', 'href' => '/b', 'sort' => 0]);
        NavRegistry::addRaw('navGroups.demo', ['labelKey' => 'c', 'href' => '/c', 'sort' => 0]);

        $groups = NavRegistry::forUser($this->actingAsSuperAdmin());

        $this->assertSame(['b', 'c', 'a'], array_column($groups[0]['items'], 'labelKey'));
    }
}

class PartialCluster extends \App\Admin\Navigation\Cluster
{
    public static string $slug = 'partial';

    public static ?string $groupLabelKey = 'navGroups.demo';

    public static ?string $permission = null;

    public static function items(): array
    {
        return [
            \App\Admin\Navigation\NavItem::make('clusters.partial.allowed.label')
                ->url('/admin/partial/allowed')
                ->permission('demo.view'),
            \App\Admin\Navigation\NavItem::make('clusters.partial.denied.label')
                ->url('/admin/partial/denied')
                ->permission('backups.delete'),
        ];
    }
}
