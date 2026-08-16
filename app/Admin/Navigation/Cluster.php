<?php

namespace App\Admin\Navigation;

/**
 * A cluster groups resources under one collapsible sidebar entry and, when
 * $prefixesUrls is true, under one URL segment.
 *
 * Membership merges from both directions: a cluster may enumerate its children
 * here, and a resource may declare itself into a cluster by pushing a NavItem
 * through NavRegistry::add().
 */
abstract class Cluster
{
    /** '/admin/{slug}/...' when $prefixesUrls. */
    public static string $slug = '';

    public static string $icon = 'Folder';

    /** null → config('myra.nav_group'). */
    public static ?string $groupLabelKey = null;

    public static int $sort = 0;

    /** null → visible whenever any child is visible. */
    public static ?string $permission = null;

    public static bool $prefixesUrls = true;

    public static function labelKey(): string
    {
        return 'clusters.'.static::$slug.'.label';
    }

    /** @return NavItem[] */
    public static function items(): array
    {
        return [];
    }
}
