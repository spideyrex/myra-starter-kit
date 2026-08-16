<?php

namespace App\Admin\Clusters;

use App\Admin\Navigation\Cluster;
use App\Admin\Navigation\NavItem;

/**
 * The shipped demo cluster: a nested resource (courses → lessons) and a singular
 * resource (site identity) under one collapsible sidebar entry.
 *
 * Every entry requires `demo.view`, so it adds nothing to a sidebar whose user
 * cannot already reach the feature demos.
 */
class LearningCluster extends Cluster
{
    public static string $slug = 'learning';

    public static string $icon = 'GraduationCap';

    public static ?string $groupLabelKey = 'navGroups.demo';

    public static ?string $permission = 'demo.view';

    public static int $sort = 0;

    public static function items(): array
    {
        return [
            NavItem::make('clusters.learning.courses.label')
                ->route('admin.learning.courses.index')
                ->icon('BookOpen')
                ->permission('demo.view')
                ->sort(0),

            NavItem::make('clusters.learning.siteIdentity.label')
                ->route('admin.learning.site-identity.show')
                ->icon('Settings')
                ->permission('demo.view')
                ->sort(10),
        ];
    }
}
