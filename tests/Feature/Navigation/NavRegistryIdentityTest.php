<?php

namespace Tests\Feature\Navigation;

use App\Admin\Navigation\NavRegistry;
use Tests\TestCase;

/**
 * The live-site proof: with nothing registered the server contributes nothing,
 * so the client merge is the identity operation and the sidebar is unchanged.
 */
class NavRegistryIdentityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        NavRegistry::flush();
    }

    protected function tearDown(): void
    {
        NavRegistry::flush();
        parent::tearDown();
    }

    /**
     * THE DEPLOY PROOF. No config() override here on purpose: this asserts the
     * config file as it ships, so upgrading a live site adds nothing to the
     * sidebar of a super-admin (who bypasses every ability via Gate::before).
     */
    public function test_the_shipped_config_contributes_nothing(): void
    {
        $this->assertSame(
            [],
            config('myra.clusters'),
            'config/myra.php must ship an empty cluster list — the demo cluster is opt-in via MYRA_DEMO_CLUSTERS.',
        );

        $user = $this->actingAsSuperAdmin();

        $this->assertSame([], NavRegistry::forUser($user));
        $this->assertSame([], $this->get(route('dashboard'))->viewData('page')['props']['myraNav']);
    }

    public function test_no_clusters_means_an_empty_registry(): void
    {
        config(['myra.clusters' => []]);

        $user = $this->actingAsSuperAdmin();

        $this->assertSame([], NavRegistry::forUser($user));
        $this->assertSame([], NavRegistry::forUser(null));
    }

    public function test_the_shared_inertia_prop_is_an_empty_array(): void
    {
        config(['myra.clusters' => []]);

        $this->actingAsSuperAdmin();

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $this->assertSame([], $response->viewData('page')['props']['myraNav']);
    }

    public function test_a_cluster_whose_slug_and_items_are_empty_registers_no_group(): void
    {
        config(['myra.clusters' => [EmptyCluster::class]]);

        $user = $this->actingAsSuperAdmin();

        // No children and no href: an empty expander must never render.
        $this->assertSame([], NavRegistry::forUser($user));
    }

    public function test_seeding_is_idempotent(): void
    {
        config(['myra.clusters' => [\App\Admin\Clusters\LearningCluster::class]]);

        $user = $this->actingAsSuperAdmin();

        NavRegistry::seedClusters();
        NavRegistry::seedClusters();
        NavRegistry::seedClusters();

        $groups = NavRegistry::forUser($user);

        $this->assertCount(1, $groups);
        $this->assertCount(1, $groups[0]['items']);
    }
}

class EmptyCluster extends \App\Admin\Navigation\Cluster
{
    public static string $slug = 'empty';

    public static ?string $groupLabelKey = 'navGroups.demo';
}
