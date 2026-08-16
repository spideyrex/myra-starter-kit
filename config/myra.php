<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Framework version
    |--------------------------------------------------------------------------
    */
    'version' => '2.3.0',

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
    */

    'clusters' => [
        \App\Admin\Clusters\LearningCluster::class,
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

];
