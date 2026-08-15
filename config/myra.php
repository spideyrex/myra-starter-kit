<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Framework version
    |--------------------------------------------------------------------------
    */
    'version' => '2.1.0',

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

];
