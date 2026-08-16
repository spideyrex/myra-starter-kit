<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Framework version
    |--------------------------------------------------------------------------
    */
    'version' => '2.6.1',

    /*
    |--------------------------------------------------------------------------
    | Scaffolding defaults
    |--------------------------------------------------------------------------
    |
    | Used by the make:myra-* commands when auto-wiring a nav item. `nav_group`
    | is the sidebar group label new pages are added under; `nav_icon` is the
    | default lucide-vue-next icon name.
    |
    */

    'nav_group' => 'Custom',
    'nav_icon' => 'LayoutGrid',

    // >>> MYRA v2.4 [A] START
    /*
    |--------------------------------------------------------------------------
    | Extensions (plugins)
    |--------------------------------------------------------------------------
    |
    | Plugins are listed EXPLICITLY. There is no composer auto-discovery: a
    | plugin registers routes inside the admin middleware stack and merges
    | RBAC modules, so installing a package must never be enough on its own.
    | A failing plugin is ALWAYS quarantined by default, in every environment —
    | it must never take the admin down. `strict` is opt-in and rethrows
    | instead; use it in CI when a broken plugin should fail the build.
    |
    */
    'extensions' => [
        'strict'  => env('MYRA_PLUGINS_STRICT', false),
        'plugins' => [
            \App\Plugins\Example\ExamplePlugin::class,
            // myra:plugins — make:myra-plugin inserts classes above this line.
        ],
    ],

    /** Per-install plugin configuration, read via YourPlugin::config('key'). */
    'plugin_config' => [],
    // <<< MYRA v2.4 [A] END

    // >>> MYRA v2.6 [A] START
    /*
    |--------------------------------------------------------------------------
    | shadcn block catalogue
    |--------------------------------------------------------------------------
    |
    | Read-only reference pages built from the vendored blocks in
    | resources/js/blocks/. With `enabled` false every route 404s and the
    | catalogue is unreachable; nothing else in the admin changes either way,
    | because a block is never imported outside the preview page.
    |
    */

    'blocks' => [
        'enabled' => env('MYRA_BLOCKS', true),
        'viewport' => ['full', '1024', '768', '375'],
    ],
    // <<< MYRA v2.6 [A] END

    // >>> MYRA v2.5 [A] START
    /*
    |--------------------------------------------------------------------------
    | Dashboard layouts
    |--------------------------------------------------------------------------
    |
    | Editing is OFF until an operator turns it on. With it false the editor bar
    | never renders, the routes still exist but the ability is unheld by default
    | on every role except admin/super-admin, and a saved row is still HONOURED —
    | turning the flag off must not silently discard a layout a user already
    | saved. `catalogue` ships EMPTY: with it empty the sheet shows an empty
    | state and nothing on the dashboard changes.
    |
    */

    'dashboard' => [
        'editable'      => env('MYRA_DASHBOARD_EDITABLE', false),
        'keys'          => ['admin.dashboard' => 'dashboardLayout.keys.main'],
        'max_entries'   => 48,
        'max_instances' => 12,
        'catalogue'     => [],
    ],
    // <<< MYRA v2.5 [A] END

    /*
    |--------------------------------------------------------------------------
    | Component registry
    |--------------------------------------------------------------------------
    |
    | `php artisan make:myra-component {Name} {keyword}` clones the matching
    | Feature Demo page (resources/js/Pages/Admin/Demo/{demo}.vue) into a new
    | admin page and generates a controller. Each entry:
    |
    |   demo    → demo Vue basename to clone
    |   method  → App\Admin\Traits\HasSampleData provider for the page's props
    |             (null = static page, controller just renders)
    |   request → whether that provider needs the Request
    |   label   → human label (nav title / docs)
    |
    */

    'components' => [
        // Static (self-contained) demos — trivial controller
        'form-builder'       => ['demo' => 'FormBuilder',       'method' => null, 'request' => false, 'label' => 'Form Builder'],
        'rich-text-editor'   => ['demo' => 'RichTextEditor',    'method' => null, 'request' => false, 'label' => 'Rich Text Editor'],
        'repeater-field'     => ['demo' => 'RepeaterField',     'method' => null, 'request' => false, 'label' => 'Repeater Field'],
        'conditional-fields' => ['demo' => 'ConditionalFields', 'method' => null, 'request' => false, 'label' => 'Conditional Fields'],
        'wizard'             => ['demo' => 'WizardDemo',        'method' => null, 'request' => false, 'label' => 'Wizard'],
        'field-types'        => ['demo' => 'FieldTypes',        'method' => null, 'request' => false, 'label' => 'Field Types'],
        'global-search'      => ['demo' => 'GlobalSearch',      'method' => null, 'request' => false, 'label' => 'Global Search'],

        // Data-driven demos — controller pulls mock props from HasSampleData
        'data-table'         => ['demo' => 'BulkActions',     'method' => 'forBulkActions',     'request' => true,  'label' => 'Data Table'],
        'bulk-actions'       => ['demo' => 'BulkActions',     'method' => 'forBulkActions',     'request' => true,  'label' => 'Bulk Actions'],
        'advanced-filters'   => ['demo' => 'AdvancedFilters', 'method' => 'forAdvancedFilters', 'request' => true,  'label' => 'Advanced Filters'],
        'inline-editing'     => ['demo' => 'InlineEditing',   'method' => 'forInlineEditing',   'request' => true,  'label' => 'Inline Editing'],
        'grouping'           => ['demo' => 'Grouping',        'method' => 'forGrouping',        'request' => true,  'label' => 'Row Grouping'],
        'import-export'      => ['demo' => 'ImportExport',    'method' => 'forImportExport',    'request' => true,  'label' => 'Import / Export'],
        'action-modals'      => ['demo' => 'ActionModals',    'method' => 'forActionModals',    'request' => true,  'label' => 'Action Modals'],
        'soft-deletes'       => ['demo' => 'SoftDeletes',     'method' => 'forSoftDeletes',     'request' => true,  'label' => 'Soft Deletes'],
        'infolist'           => ['demo' => 'Infolist',        'method' => 'forInfolist',        'request' => false, 'label' => 'Infolist'],
        'relation-manager'   => ['demo' => 'RelationManager', 'method' => 'forRelationManager', 'request' => true,  'label' => 'Relation Manager'],
        'widgets'            => ['demo' => 'Widgets',         'method' => 'forWidgets',         'request' => false, 'label' => 'Dashboard Widgets'],
        'reordering'         => ['demo' => 'Reordering',      'method' => 'forReordering',      'request' => false, 'label' => 'Reordering'],
        'map'                => ['demo' => 'Map',             'method' => null,                 'request' => false, 'label' => 'Map'],
        'saved-views'        => ['demo' => 'SavedViews',      'method' => 'forSavedViews',      'request' => true,  'label' => 'Saved Views'],
        'reports'            => ['demo' => 'Reports',         'method' => null,                 'request' => false, 'label' => 'Reports & Charts'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Saved table views
    |--------------------------------------------------------------------------
    |
    | `max` caps how many views one user may store per table_key.
    |
    */

    'views' => [
        'max' => 25,
    ],

    // >>> MYRA v2.4 [B] START
    /*
    |--------------------------------------------------------------------------
    | Clusters and server-contributed navigation
    |--------------------------------------------------------------------------
    |
    | Clusters group resources under one collapsible sidebar entry and, when
    | $prefixesUrls is true, under one URL segment. With this list empty the
    | `myraNav` Inertia prop serialises as [] and the sidebar is byte-identical
    | to v2.3 — merging an empty server list is the identity operation.
    |
    | Ships EMPTY on purpose: upgrading to v2.4 must not add an entry to a live
    | sidebar. The bundled Learning demo is opt-in via MYRA_DEMO_CLUSTERS=true;
    | its pages stay reachable by URL either way.
    |
    */

    'clusters' => [
        ...(env('MYRA_DEMO_CLUSTERS', false) ? [\App\Admin\Clusters\LearningCluster::class] : []),
        // myra:clusters — make:myra-cluster inserts clusters above this line.
    ],
    // <<< MYRA v2.4 [B] END

    /*
    |--------------------------------------------------------------------------
    | Inline uploads
    |--------------------------------------------------------------------------
    |
    | Limits for the markdown editor's paste/drop image endpoint. Files are
    | stored on the private `local` disk and served back through a
    | permission-gated route.
    |
    */

    'uploads' => [
        'max_kb' => 5120,
        'accept' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ],

    // >>> MYRA v2.4 [C] START
    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy — OPT-IN, DEFAULT OFF
    |--------------------------------------------------------------------------
    |
    | With `enabled` false NOTHING is registered: no global scope, no creating
    | hook, no validation-rule change, no middleware, no Inertia prop. The
    | disabled path is asserted byte-identical against a committed SQL baseline
    | in tests/Feature/Tenancy/DisabledPathIsNoOpTest.
    |
    | `models` is a second, independent lock: a model may carry BelongsToTenant
    | and still not be scoped until it is named here.
    |
    | `enabled` is compared with ===, so MYRA_TENANCY must be literally `true`.
    | Anything else — including `1` — leaves tenancy off.
    |
    */
    'tenancy' => [
        'enabled'            => env('MYRA_TENANCY', false),
        'model'              => \App\Models\Team::class,
        'column'             => 'team_id',
        'null_rows'          => 'strict',   // 'strict' | 'shared'
        'super_admin_bypass' => true,
        'models'             => [],

        // Rows that belong to a tenant through the team pivot rather than a
        // tenant column — `users` is the only such table today.
        'membership_table'    => 'team_user',
        'membership_user_key' => 'user_id',
        'membership_tables'   => ['users'],

        // The public, unauthenticated surface. A guest resolves no tenant and
        // the predicate fails closed, so scoping the public site would blank it
        // the day Article/Page/Category are listed above. Opt in only if the
        // public site really is per-tenant (and a tenant is resolvable there).
        'scope_public'        => false,

        // Tables an operator has DELIBERATELY declared tenant-shared. Anything
        // not listed here, not carrying the tenant column and not scoped by
        // membership returns zero rows rather than every tenant's rows.
        'shared_tables'       => [],
    ],
    // <<< MYRA v2.4 [C] END

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    |
    | Server-streamed exports (App\Admin\Export). `max_rows` is a refusal, not a
    | truncation: over the cap the request 422s rather than hand back a partial
    | file the user mistakes for complete.
    |
    */

    'exports' => [
        'max_rows' => 50000,
        'chunk_size' => 1000,
        'formats' => ['csv', 'xlsx'],
        'client_max_rows' => 5000,
    ],

    // >>> MYRA v2.4 [D] START
    /*
    |--------------------------------------------------------------------------
    | Scale
    |--------------------------------------------------------------------------
    |
    | `stable_sort` adds an id tiebreak to the LENGTH-AWARE path. It is off by
    | default because turning it on changes the SQL of every existing admin
    | table (the row set is unchanged; which page a tied row lands on is not).
    | The cursor path always applies the tiebreak — it is a correctness
    | requirement there, not a preference.
    |
    */

    'performance' => [
        'stable_sort'          => env('MYRA_STABLE_SORT', false),
        'virtualize_above'     => 200,
        'row_height'           => 44,
        'viewport_height'      => 600,
        'overscan'             => 8,
        'assert_indexed_sorts' => env('MYRA_ASSERT_INDEXES', false),
    ],
    // <<< MYRA v2.4 [D] END

    /*
    |--------------------------------------------------------------------------
    | Imports
    |--------------------------------------------------------------------------
    |
    | `resources` maps the {resource} URL segment to a class exposing a static
    | definition(): App\Admin\Import\ImportDefinition. No migrations, no queue,
    | no notifications table — chunk_size is the future job boundary.
    |
    */

    'imports' => [
        'max_rows' => 50000,
        'max_columns' => 128,
        'chunk_size' => 250,
        'token_ttl' => 60,
        'max_failures' => 1000,

        'resources' => [
            'users' => \App\Admin\Import\UsersImport::class,
            'demo-contacts' => \App\Admin\Import\DemoContactsImport::class,
        ],
    ],

    // >>> MYRA v2.2 [D] START
    /*
    |--------------------------------------------------------------------------
    | Query builder
    |--------------------------------------------------------------------------
    |
    | Server-side ceilings for a submitted rule tree. A tree above any of these
    | is a 422 — never a silently ignored constraint.
    |
    */

    'filters' => [
        'max_rules' => 25,
        'max_depth' => 3,
        'max_bytes' => 16384,
    ],

    /*
    |--------------------------------------------------------------------------
    | Global search
    |--------------------------------------------------------------------------
    */

    'search' => [
        'max_results' => 40,
        'min_term' => 2,
        'max_term' => 100,
    ],
    // <<< MYRA v2.2 [D] END

    // >>> MYRA v2.3 [B] START
    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    |
    | Every number a report produces is computed by the database. These are the
    | ceilings that keep it that way: `max_groups` caps the rows a grouped
    | statement may return (over it is a 422, never a truncated chart) and
    | `max_period_days` caps the window a client may ask for.
    |
    | `definitions` maps the {report} URL segment to a class exposing a static
    | definition(): App\Admin\Report\ReportDefinition.
    |
    */

    'reports' => [
        'max_groups'      => 200,
        'hard_max_groups' => 2000,
        'max_measures'    => 6,
        'max_period_days' => 1830,
        'max_batch'       => 12,
        'cache_ttl'       => 120,
        'formats'         => ['csv', 'xlsx', 'pdf'],
        'definitions'     => [
            'users'    => \App\Admin\Report\Reports\UsersReport::class,
            'activity' => \App\Admin\Report\Reports\ActivityReport::class,
            // myra:reports — make:myra-report inserts definitions above this line.
        ],
    ],
    // <<< MYRA v2.3 [B] END

    // >>> MYRA v2.5 [B] START
    /*
    |--------------------------------------------------------------------------
    | Realtime widgets
    |--------------------------------------------------------------------------
    |
    | OFF. With `enabled` false WidgetSignal is a no-op, no event is ever
    | dispatched, and the dashboard bus falls back to polling. BROADCAST_CONNECTION
    | is `log` on this deployment, so nothing reaches a socket regardless, and
    | without VITE_REVERB_APP_KEY the client never opens one either.
    |
    | The broadcast payload is a CHANGE SIGNAL — report keys and a timestamp,
    | never rows or aggregates. On receipt the client refetches through the
    | existing Gate-checked, ownership-scoped widget batch endpoint.
    |
    */

    'realtime' => [
        'enabled'      => env('MYRA_REALTIME', false),
        'coalesce_ms'  => 400,
        'default_poll' => 120,
    ],
    // <<< MYRA v2.5 [B] END

    // >>> MYRA v2.6 [B] START
    /*
    |--------------------------------------------------------------------------
    | shadcn examples
    |--------------------------------------------------------------------------
    |
    | The example catalogue is a reference surface behind `examples.view`. With
    | this false every route 404s and the catalogue is unreachable; the vendored
    | source stays on disk either way, because it is never imported by anything
    | outside resources/js/Pages/Admin/Examples/Preview.vue.
    |
    */

    'examples' => [
        'enabled' => env('MYRA_EXAMPLES', true),
    ],
    // <<< MYRA v2.6 [B] END

    // >>> MYRA v2.3 [C] START
    /*
    |--------------------------------------------------------------------------
    | Charts
    |--------------------------------------------------------------------------
    */

    'charts' => [
        'default_type'    => 'bar',
        'sparkline_points'=> 30,
        'lazy'            => true,
    ],
    // <<< MYRA v2.3 [C] END

    // >>> MYRA v2.5 [C] START
    /*
    |--------------------------------------------------------------------------
    | Component gallery
    |--------------------------------------------------------------------------
    |
    | The playground is a demo-only surface behind `demo.view`, so it is safe
    | on — but it is still a flag, because "safe" is a judgement and a flag is
    | a fact. With it false the Playground page renders a disabled notice and
    | the gallery cards stop advertising a playground.
    |
    */

    'gallery' => [
        'playgrounds' => env('MYRA_GALLERY_PLAYGROUNDS', true),
    ],
    // <<< MYRA v2.5 [C] END

    // >>> MYRA v2.6 [C] START
    /*
    |--------------------------------------------------------------------------
    | Brand manager
    |--------------------------------------------------------------------------
    |
    | `probe_ttl` bounds how long a FOREIGN write (tinker, a seeder, a second
    | web node with its own cache) can stay invisible. App-path writes are
    | invalidated instantly by BrandCacheSubscriber; the probe is only the
    | safety net. 0 disables it — the event path still works.
    |
    */
    'brand' => [
        'probe_ttl' => env('MYRA_BRAND_PROBE_TTL', 60),
        'cache_ttl' => 3600,
        'min_contrast' => 4.5,
    ],
    // <<< MYRA v2.6 [C] END

    // >>> MYRA v2.3 [D] START
    /*
    |--------------------------------------------------------------------------
    | Scheduled report delivery
    |--------------------------------------------------------------------------
    */

    'report_schedules' => [
        'max_per_user'      => 20,
        'max_recipients'    => 25,
        'pause_after_fails' => 3,
        'dispatch_limit'    => 200,
        'dispatch_window'   => 15,
        'pdf_disk'          => 'local',
        'pdf_ttl_minutes'   => 60,
    ],
    // <<< MYRA v2.3 [D] END

    // >>> MYRA v2.6 [D] START
    /*
    |--------------------------------------------------------------------------
    | Landing page templates
    |--------------------------------------------------------------------------
    |
    | Arrangement and chrome only — every template renders the same
    | HomepageSettings payload. An unregistered key always degrades to
    | 'classic', so the public homepage cannot 500 on a stale setting.
    |
    */

    'landing' => [
        'templates' => [
            \App\Homepage\Templates\ClassicTemplate::class,
            \App\Homepage\Templates\SpotlightTemplate::class,
            \App\Homepage\Templates\EditorialTemplate::class,
            \App\Homepage\Templates\SaasTemplate::class,
            \App\Homepage\Templates\MinimalTemplate::class,
            \App\Homepage\Templates\DocsTemplate::class,
            // myra:landing — make:myra-landing inserts templates above this line.
        ],
    ],
    // <<< MYRA v2.6 [D] END

    // >>> MYRA v2.5 [D] START
    /*
    |--------------------------------------------------------------------------
    | AI surfaces
    |--------------------------------------------------------------------------
    |
    | Every AI surface is OFF until an admin turns it on. Shipping dark is the
    | only safe default on a live site. With these false the routes 404.
    |
    */

    'ai' => [
        'features' => [
            'filter'    => env('MYRA_AI_FILTER', false),
            'schema'    => env('MYRA_AI_SCHEMA', false),
            'summarise' => env('MYRA_AI_SUMMARISE', false),
        ],
        // POST admin/ai/assist has no permission gate today. Turning this on
        // adds one; false keeps v2.4.0 behaviour byte-for-byte.
        'gate_assist'         => env('MYRA_AI_GATE_ASSIST', false),
        'max_prompt'          => 500,
        'json_max_bytes'      => 8192,
        'summary_max_buckets' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | PWA / offline shell
    |--------------------------------------------------------------------------
    |
    | OFF. With this false: no manifest link is rendered, myra-sw.js is never
    | registered, any previously-registered myra-sw.js is actively unregistered
    | and its caches purged, and the FCM registration keeps its CURRENT root
    | scope untouched.
    |
    */

    'pwa' => [
        'enabled' => env('MYRA_PWA', false),
    ],
    // <<< MYRA v2.5 [D] END

];
