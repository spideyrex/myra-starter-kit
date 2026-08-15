<?php

namespace App\Http\Controllers\Admin;

use App\Admin\QueryBuilder\FieldSet;
use App\Admin\QueryBuilder\FieldSpec;
use App\Admin\Traits\HandlesQueryBuilder;
use App\Admin\Traits\SearchableQuery;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

use Inertia\Inertia;

class DemoController extends Controller
{
    use HandlesQueryBuilder, SearchableQuery;

    /**
     * Seed faker so demo data is deterministic across requests.
     */
    private function seedFaker(int $seed = 42): void
    {
        fake()->seed($seed);
    }

    // =========================================================================
    // Static / Client-only demos (no data needed)
    // =========================================================================

    public function index()
    {
        return Inertia::render('Admin/Demo/Index');
    }

    public function richTextEditor()
    {
        return Inertia::render('Admin/Demo/RichTextEditor');
    }

    public function repeaterField()
    {
        return Inertia::render('Admin/Demo/RepeaterField');
    }

    public function formBuilder()
    {
        return Inertia::render('Admin/Demo/FormBuilder');
    }

    public function conditionalFields()
    {
        return Inertia::render('Admin/Demo/ConditionalFields');
    }

    public function wizardDemo()
    {
        return Inertia::render('Admin/Demo/WizardDemo');
    }

    public function globalSearch()
    {
        return Inertia::render('Admin/Demo/GlobalSearch');
    }

    public function fieldTypes()
    {
        return Inertia::render('Admin/Demo/FieldTypes');
    }

    public function codeEditor()
    {
        return Inertia::render('Admin/Demo/CodeEditor');
    }

    public function map()
    {
        return Inertia::render('Admin/Demo/Map');
    }

    // >>> MYRA v2.2 [D] START
    /**
     * Backed by a real Eloquent model so the query builder compiles to SQL
     * rather than filtering an in-memory Collection.
     */
    public function advancedFilters(Request $request)
    {
        $actor = $request->user();

        $query = User::query()
            ->with('roles')
            // The demo's own ownership scope. A top-level `or` inside a rule
            // tree can never escape it — RuleCompiler wraps the whole tree.
            ->when(
                ! $actor?->hasRole(config('shield.super_admin_role', 'super-admin')),
                fn (Builder $q) => $q->where('created_by', $actor?->id),
            )
            ->when($request->status, fn (Builder $q, $s) => $q->where('status', $s))
            ->when($request->filled('verified'), fn (Builder $q) => $request->get('verified') === '1'
                ? $q->whereNotNull('email_verified_at')
                : $q->whereNull('email_verified_at'))
            ->when($request->get('has_phone') === '1', fn (Builder $q) => $q->whereNotNull('phone'))
            ->when($request->filled('created_from'), fn (Builder $q) => $q->whereDate('created_at', '>=', $request->input('created_from')))
            ->when($request->filled('created_to'), fn (Builder $q) => $q->whereDate('created_at', '<=', $request->input('created_to')));

        $query = $this->applyQueryBuilder($query, $request, 'query_builder', $this->demoFieldSet());

        $filterKeys = ['search', 'sort', 'direction', 'status', 'verified', 'has_phone', 'created_from', 'created_to', 'query_builder'];

        return Inertia::render('Admin/Demo/AdvancedFilters', [
            'records' => $this->applySearchAndPaginate(
                $query,
                $request,
                searchable: ['name', 'email'],
                sortable: ['name', 'email', 'status', 'created_at'],
            ),
            'filters' => (object) $request->only($filterKeys),
            'constraints' => $this->demoFieldSet()->toClientSchema($actor),
        ]);
    }

    /** The server-side whitelist — the client's constraint list carries no authority. */
    private function demoFieldSet(): FieldSet
    {
        return FieldSet::make([
            FieldSpec::text('name')->labelKey('filters.field.name')->contains(),
            FieldSpec::text('email')->labelKey('filters.field.email')->contains(),
            FieldSpec::text('phone')->labelKey('filters.field.phone')->nullable(),
            FieldSpec::select('status')->labelKey('filters.field.status')->options(['active', 'suspended', 'pending']),
            FieldSpec::date('created_at')->labelKey('filters.field.createdAt'),
            FieldSpec::date('email_verified_at')->labelKey('filters.field.verifiedAt')->nullable(),
            FieldSpec::relation('roles')->labelKey('filters.field.roles')->relationship('roles', 'name'),
        ])->maxRules((int) config('myra.filters.max_rules', 25))
            ->maxDepth((int) config('myra.filters.max_depth', 3));
    }
    // <<< MYRA v2.2 [D] END

    // =========================================================================
    // Data-driven demos
    // =========================================================================

    public function bulkActions(Request $request)
    {
        $products = $this->paginateCollection(
            $this->generateProducts(),
            $request,
            ['category', 'status']
        );

        return Inertia::render('Admin/Demo/BulkActions', [
            'products' => $products,
            'filters' => (object) $request->only('search', 'sort', 'direction', 'category', 'status'),
        ]);
    }

    public function softDeletes(Request $request)
    {
        $this->seedFaker(100);

        $names = collect(range(1, 40))->map(fn () => fake()->name());

        $items = collect(range(1, 40))->map(fn ($i) => [
            'id' => $i,
            'name' => $names[$i - 1],
            'email' => "user{$i}@example.com",
            'status' => $i % 5 === 0 ? 'suspended' : 'active',
            'deleted_at' => $i % 4 === 0 ? now()->subDays($i + 5)->toDateTimeString() : null,
            'created_at' => now()->subDays(30 + $i * 8)->toDateTimeString(),
        ]);

        $trashed = $request->get('trashed', '');
        if ($trashed === 'only') {
            $items = $items->filter(fn ($item) => $item['deleted_at'] !== null);
        } elseif ($trashed !== 'with') {
            $items = $items->filter(fn ($item) => $item['deleted_at'] === null);
        }

        if ($search = $request->get('search')) {
            $items = $items->filter(fn ($item) =>
                str_contains(strtolower($item['name']), strtolower($search)) ||
                str_contains(strtolower($item['email']), strtolower($search))
            );
        }

        $paginated = $this->manualPaginate($items, $request);

        return Inertia::render('Admin/Demo/SoftDeletes', [
            'users' => $paginated,
            'filters' => (object) $request->only('search', 'sort', 'direction', 'trashed'),
        ]);
    }

    public function actionModals(Request $request)
    {
        $this->seedFaker(200);

        $taskTitles = [
            'Design landing page mockup', 'Fix login bug on mobile', 'Write API documentation',
            'Set up CI/CD pipeline', 'Optimize database queries', 'Add user onboarding flow',
            'Implement password reset', 'Create email templates', 'Review pull requests',
            'Migrate to new server', 'Add analytics tracking', 'Refactor auth module',
            'Build notification system', 'Update dependencies', 'Add dark mode support',
            'Write unit tests', 'Fix pagination issues', 'Design settings page',
            'Implement file uploads', 'Add search functionality', 'Create admin dashboard',
            'Fix responsive layout', 'Add export feature', 'Optimize image loading',
            'Build user profile page', 'Implement caching', 'Add rate limiting',
            'Design error pages', 'Fix timezone handling', 'Add localization support',
        ];

        $assignees = collect(range(1, 30))->map(fn () => fake()->name());

        $items = collect(range(1, 30))->map(fn ($i) => [
            'id' => $i,
            'title' => $taskTitles[$i - 1],
            'description' => fake()->sentence(8),
            'priority' => ['low', 'medium', 'high'][($i - 1) % 3],
            'assignee' => $assignees[$i - 1],
            'status' => ['open', 'in_progress', 'completed'][($i - 1) % 3],
            'created_at' => now()->subDays($i * 3)->toDateTimeString(),
        ]);

        if ($search = $request->get('search')) {
            $items = $items->filter(fn ($item) =>
                str_contains(strtolower($item['title']), strtolower($search)) ||
                str_contains(strtolower($item['assignee']), strtolower($search))
            );
        }

        $paginated = $this->manualPaginate($items, $request);

        return Inertia::render('Admin/Demo/ActionModals', [
            'tasks' => $paginated,
            'filters' => (object) $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function importExport(Request $request)
    {
        $this->seedFaker(300);

        $contacts = collect(range(1, 25))->map(fn ($i) => [
            'id' => $i,
            'name' => fake()->name(),
            'email' => "contact{$i}@example.com",
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'created_at' => now()->subDays($i * 7)->toDateTimeString(),
        ]);

        if ($search = $request->get('search')) {
            $contacts = $contacts->filter(fn ($item) =>
                str_contains(strtolower($item['name']), strtolower($search)) ||
                str_contains(strtolower($item['email']), strtolower($search)) ||
                str_contains(strtolower($item['company']), strtolower($search))
            );
        }

        $paginated = $this->manualPaginate($contacts, $request);

        return Inertia::render('Admin/Demo/ImportExport', [
            'contacts' => $paginated,
            'filters' => (object) $request->only('search', 'sort', 'direction'),
        ]);
    }

    // --- Feature 1: Inline Editing ---

    public function inlineEditing(Request $request)
    {
        // Demo has no database — inline edits live in the session for this demo only.
        $edits = $request->session()->get('demo.inline', []);

        $products = $this->generateProducts()->map(function ($item) use ($edits) {
            $item['is_active'] = $item['status'] === 'active';
            foreach ($edits[$item['id']] ?? [] as $field => $value) {
                $item[$field] = $value;
            }
            return $item;
        });

        $paginated = $this->paginateCollection($products, $request, ['category', 'status']);

        return Inertia::render('Admin/Demo/InlineEditing', [
            'products' => $paginated,
            'filters' => (object) $request->only('search', 'sort', 'direction', 'category', 'status'),
        ]);
    }

    public function demoInlineUpdate(Request $request, int $id)
    {
        // Client-supplied field names are always whitelisted server-side.
        $casts = [
            'category' => 'string',
            'price' => 'string',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'brand_color' => 'string',
        ];

        $data = $request->validate([
            'field' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys($casts))],
            'value' => ['present'],
        ]);

        $value = match ($casts[$data['field']]) {
            'boolean' => $request->boolean('value'),
            'integer' => (int) $data['value'],
            default => (string) $data['value'],
        };

        $edits = $request->session()->get('demo.inline', []);
        $edits[$id][$data['field']] = $value;
        $request->session()->put('demo.inline', $edits);

        return back()->with('success', "Product #{$id}: {$data['field']} updated.");
    }

    // --- Feature 3: Infolist ---

    public function infolist()
    {
        $user = [
            'id' => 1,
            'name' => 'Jane Cooper',
            'email' => 'jane.cooper@example.com',
            'avatar' => null,
            'status' => 'active',
            'role' => 'admin',
            'phone' => '+1 (555) 123-4567',
            'company' => 'Acme Corp',
            'bio' => 'Senior software engineer with 10+ years of experience building scalable web applications. Passionate about clean code and developer experience.',
            'balance' => 12450.50,
            'orders_count' => 47,
            'email_verified' => true,
            'two_factor_enabled' => true,
            'ip_address' => '192.168.1.42',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'password_changed_at' => now()->subDays(45)->toDateTimeString(),
            'created_at' => now()->subMonths(8)->toDateTimeString(),
            'last_login_at' => now()->subHours(2)->toDateTimeString(),
            'metadata' => [
                'Timezone' => 'America/New_York',
                'Language' => 'English',
                'Theme' => 'System',
                'Signup Source' => 'Google',
            ],
            'recent_activity' => [
                ['action' => 'Updated profile', 'date' => now()->subHours(2)->toDateTimeString(), 'ip' => '192.168.1.42'],
                ['action' => 'Changed password', 'date' => now()->subDays(3)->toDateTimeString(), 'ip' => '192.168.1.42'],
                ['action' => 'Logged in', 'date' => now()->subDays(5)->toDateTimeString(), 'ip' => '10.0.0.1'],
            ],
            // ColorEntry demo values: hex, alpha (checkerboard), and an invalid value.
            'primary_color' => '#6366f1',
            'accent_color' => '#f59e0b',
            'overlay_color' => 'rgba(0, 0, 0, 0.4)',
            'legacy_color' => 'red; background-image: url(x)',
            // CodeEntry demo values.
            'webhook_response' => json_encode([
                'id' => 'evt_8Fq2Lb',
                'status' => 'delivered',
                'attempts' => 2,
                'error' => null,
                'endpoint' => 'https://hooks.example.com/myra',
                'headers' => ['content-type' => 'application/json', 'x-signature' => 'sha256=…'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'deploy_script' => implode("\n", [
                '#!/usr/bin/env bash',
                'set -euo pipefail',
                '',
                'php artisan down --render="errors::503"',
                'composer install --no-dev --optimize-autoloader',
                'php artisan migrate --force',
                'npm ci && npm run build',
                'php artisan up',
            ]),
            // Object value: CodeBlock stringifies it and forces JSON.
            'raw_payload' => ['event' => 'user.updated', 'data' => ['id' => 1, 'name' => 'Jane Cooper']],
            // Long document to exercise maxLines + Expand.
            'access_log' => collect(range(1, 900))
                ->map(fn ($i) => sprintf('10.0.0.%d - - [%s] "GET /api/users?page=%d HTTP/1.1" 200 %d', $i % 255, now()->toDateTimeString(), $i, 400 + $i))
                ->implode("\n"),
        ];

        return Inertia::render('Admin/Demo/Infolist', ['user' => $user]);
    }

    // --- Feature 4: Relation Manager ---

    public function relationManager(Request $request)
    {
        $user = [
            'id' => 1,
            'name' => 'Jane Cooper',
            'email' => 'jane.cooper@example.com',
            'avatar' => null,
            'status' => 'active',
            'email_verified' => true,
            'created_at' => now()->subMonths(8)->toDateTimeString(),
        ];

        $this->seedFaker(400);

        $orders = collect(range(1, 25))->map(fn ($i) => [
            'id' => $i,
            'order_number' => 'ORD-' . str_pad($i, 5, '0', STR_PAD_LEFT),
            'total' => round(($i * 37 + 1999) / 100, 2),
            'status' => ['completed', 'processing', 'pending', 'cancelled'][($i - 1) % 4],
            'created_at' => now()->subDays($i * 7)->toDateTimeString(),
        ]);

        $activities = collect(range(1, 15))->map(fn ($i) => [
            'id' => $i,
            'description' => ['Updated profile', 'Changed password', 'Placed order', 'Logged in', 'Uploaded avatar'][($i - 1) % 5],
            'subject' => ['Profile', 'Security', 'Order #' . (100 + $i), null, 'Media'][($i - 1) % 5],
            'created_at' => now()->subDays($i * 4)->toDateTimeString(),
        ]);

        // Activities: clone request and remap act_ prefixed params to standard names
        $actRequest = clone $request;
        $actRequest->merge([
            'sort' => $request->get('act_sort', 'id'),
            'direction' => $request->get('act_direction', 'asc'),
        ]);

        return Inertia::render('Admin/Demo/RelationManager', [
            'user' => $user,
            'orders' => $this->manualPaginate($orders, $request),
            'activities' => $this->manualPaginate($activities, $actRequest, 10, 'act_page'),
            'filters' => (object) $request->only(
                'search', 'sort', 'direction',
                'act_search', 'act_sort', 'act_direction',
            ),
        ]);
    }

    public function demoRelationCreate(Request $request)
    {
        return back()->with('success', 'Demo: Related record created successfully.');
    }

    // --- Feature 5: Grouping ---

    public function grouping(Request $request)
    {
        $this->seedFaker(500);

        $statuses = ['completed', 'processing', 'pending', 'cancelled'];
        $customers = collect(range(1, 40))->map(fn () => fake()->name());

        $orders = collect(range(1, 40))->map(fn ($i) => [
            'id' => $i,
            'order_number' => 'ORD-' . str_pad($i, 5, '0', STR_PAD_LEFT),
            'customer' => $customers[$i - 1],
            'status' => $statuses[($i - 1) % 4],
            'quantity' => (($i * 7) % 20) + 1,
            'price' => round((($i * 137 + 999) % 49000 + 999) / 100, 2),
            'rating' => round((($i * 13) % 40 + 10) / 10, 1),
            'delivery_days' => (($i * 3) % 9) + 1,
            'is_paid' => $i % 3 !== 0,
            'is_rush' => $i % 5 === 0,
            'created_at' => now()->subDays($i * 2)->toDateTimeString(),
        ]);

        $paginated = $this->paginateCollection($orders, $request, ['status'], 40);

        return Inertia::render('Admin/Demo/Grouping', [
            'orders' => $paginated,
            'filters' => (object) $request->only('search', 'sort', 'direction', 'status'),
        ]);
    }

    // --- Feature 6: Reordering ---

    public function reordering()
    {
        $titles = [
            'Set up project scaffolding', 'Design database schema', 'Implement authentication',
            'Build API endpoints', 'Create admin dashboard', 'Add email notifications',
            'Write integration tests', 'Set up deployment pipeline', 'Configure monitoring',
            'Write user documentation', 'Performance optimization', 'Security audit',
        ];

        $tasks = collect(range(1, 12))->map(fn ($i) => [
            'id' => $i,
            'title' => $titles[$i - 1],
            'priority' => ['high', 'medium', 'low'][($i - 1) % 3],
            'sort_order' => $i,
        ])->toArray();

        return Inertia::render('Admin/Demo/Reordering', ['tasks' => $tasks]);
    }

    public function demoReorder(Request $request)
    {
        return back()->with('success', 'Order saved successfully.');
    }

    // --- Feature 7: Widgets ---

    public function widgets()
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        $salesData = [65, 78, 52, 91, 84, 107];
        $revenueData = [12400, 15800, 9200, 18600, 16100, 19800];

        return Inertia::render('Admin/Demo/Widgets', [
            'data' => [
                'totalUsers' => 1284,
                'revenue' => 45230,
                'orders' => 356,
                'conversionRate' => 3.2,
                'salesByMonth' => collect($months)->map(fn ($m, $i) => [
                    'month' => $m,
                    'sales' => $salesData[$i],
                    'revenue' => $revenueData[$i],
                ])->toArray(),
                'topProducts' => [
                    ['name' => 'Premium Plan', 'sales' => 142, 'revenue' => '$14,200'],
                    ['name' => 'Starter Kit', 'sales' => 98, 'revenue' => '$4,900'],
                    ['name' => 'Enterprise License', 'sales' => 45, 'revenue' => '$22,500'],
                    ['name' => 'Support Add-on', 'sales' => 67, 'revenue' => '$3,350'],
                ],
                'recentOrders' => [
                    ['id' => '#4021', 'customer' => 'Sarah Chen', 'total' => '$49.99', 'status' => 'Completed'],
                    ['id' => '#4020', 'customer' => 'Mike Rodriguez', 'total' => '$89.00', 'status' => 'Processing'],
                    ['id' => '#4019', 'customer' => 'Emma Wilson', 'total' => '$24.50', 'status' => 'Pending'],
                    ['id' => '#4018', 'customer' => 'James Kim', 'total' => '$67.80', 'status' => 'Completed'],
                    ['id' => '#4017', 'customer' => 'Lisa Park', 'total' => '$35.00', 'status' => 'Processing'],
                ],
            ],
        ]);
    }

    // =========================================================================
    // Action handlers (all redirect back with flash)
    // =========================================================================

    public function bulkAction(Request $request)
    {
        $count = count($request->get('ids', []));
        $action = $request->get('action', 'action');

        return back()->with('success', "Bulk {$action} performed on {$count} item(s).");
    }

    public function demoRestore(int $id)
    {
        return back()->with('success', "Item #{$id} restored successfully.");
    }

    public function demoForceDelete(int $id)
    {
        return back()->with('success', "Item #{$id} permanently deleted.");
    }

    public function demoSoftDelete(int $id)
    {
        return back()->with('success', "Item #{$id} moved to trash.");
    }

    public function demoUpdateTask(Request $request, int $id)
    {
        return back()->with('success', "Task #{$id} updated successfully.");
    }

    public function demoDeleteTask(int $id)
    {
        return back()->with('success', "Task #{$id} deleted.");
    }

    public function demoReplicateTask(int $id)
    {
        return back()->with('success', "Task #{$id} duplicated.");
    }

    public function demoArchiveTask(int $id)
    {
        return back()->with('success', "Task #{$id} archived.");
    }

    public function exportCsv()
    {
        $this->seedFaker(300);

        $items = collect(range(1, 25))->map(fn ($i) => [
            'Name' => fake()->name(),
            'Email' => "contact{$i}@example.com",
            'Phone' => fake()->phoneNumber(),
            'Company' => fake()->company(),
        ]);

        return response()->streamDownload(function () use ($items) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Email', 'Phone', 'Company']);
            foreach ($items as $item) {
                fputcsv($handle, $item);
            }
            fclose($handle);
        }, 'demo-contacts-export.csv', ['Content-Type' => 'text/csv']);
    }

    // --- Demo Import (mock responses for ImportModal) ---

    public function demoImportPreview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);
            return response()->json(['error' => 'Unable to read CSV headers.'], 422);
        }

        $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
        $headers = array_map('trim', $headers);

        $preview = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if ($rowCount < 5) {
                $preview[] = array_combine($headers, array_pad($row, count($headers), ''));
            }
            $rowCount++;
        }
        fclose($handle);

        return response()->json([
            'headers' => $headers,
            'preview' => $preview,
            'total_rows' => $rowCount,
        ]);
    }

    public function demoImportExecute(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:5120',
            'mapping' => 'required|array',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        fgetcsv($handle); // skip header
        $rowCount = 0;
        while (fgetcsv($handle) !== false) {
            $rowCount++;
        }
        fclose($handle);

        return response()->json([
            'imported' => $rowCount,
            'errors' => [],
        ]);
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    private function generateProducts(): \Illuminate\Support\Collection
    {
        $this->seedFaker(50);

        $categories = ['Electronics', 'Clothing', 'Books', 'Home & Garden'];
        $statuses = ['active', 'draft', 'archived'];

        // Deliberately mixed: hex, rgba, 8-digit hex (alpha) and one invalid value
        // so the ColorColumn demo exercises the checkerboard and the safe fallback.
        $palette = ['#2563eb', '#16a34a', 'rgba(220, 38, 38, 0.4)', '#f59e0bcc', '#7c3aed', 'not-a-color'];

        // Deterministic price tiers: mix of cheap, mid, and expensive items
        $priceTiers = [9.99, 14.50, 24.99, 39.95, 49.99, 79.00, 99.99, 149.95, 199.00, 249.50,
                       299.99, 349.00, 399.95, 449.99, 499.00, 599.99, 699.00, 799.95, 999.99, 1299.00];

        $names = collect(range(1, 50))->map(fn () => fake()->words(3, true));

        return collect(range(1, 50))->map(fn ($i) => [
            'id' => $i,
            'name' => $names[$i - 1],
            'category' => $categories[($i - 1) % 4],
            'price' => $priceTiers[($i - 1) % count($priceTiers)],
            'stock' => $i % 7 === 0 ? 0 : (($i * 23) % 500) + 1, // every 7th item is out of stock
            'status' => $statuses[($i - 1) % 3],
            'brand_color' => $palette[($i - 1) % count($palette)],
            'is_featured' => $i % 5 === 0,
            'created_at' => now()->subDays($i * 7)->toDateTimeString(),
        ]);
    }

    private function paginateCollection(
        \Illuminate\Support\Collection $items,
        Request $request,
        array $filterKeys = [],
        int $perPage = 10
    ): array {
        if ($search = $request->get('search')) {
            $searchLower = strtolower($search);
            $items = $items->filter(fn ($item) =>
                collect($item)->contains(fn ($val) =>
                    is_string($val) && str_contains(strtolower($val), $searchLower)
                )
            );
        }

        foreach ($filterKeys as $key) {
            if ($value = $request->get($key)) {
                $items = $items->filter(fn ($item) => ($item[$key] ?? '') === $value);
            }
        }

        return $this->manualPaginate($items, $request, $perPage);
    }

    private function manualPaginate(
        \Illuminate\Support\Collection $items,
        Request $request,
        int $perPage = 10,
        string $pageName = 'page'
    ): array {
        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');

        $items = $direction === 'desc'
            ? $items->sortByDesc($sort)
            : $items->sortBy($sort);

        $page = (int) $request->get($pageName, 1);
        $total = $items->count();
        $lastPage = max((int) ceil($total / $perPage), 1);
        $page = min($page, $lastPage);
        $from = $total > 0 ? ($page - 1) * $perPage + 1 : null;
        $to = $total > 0 ? min($page * $perPage, $total) : null;
        $sliced = $items->slice(($page - 1) * $perPage, $perPage)->values();

        $baseUrl = $request->url();
        $query = $request->query();

        $buildUrl = function (int $p) use ($baseUrl, $query, $pageName) {
            $query[$pageName] = $p;
            return $baseUrl . '?' . http_build_query($query);
        };

        // Build pagination links (Previous + page numbers + Next)
        $links = [];
        $links[] = ['url' => $page > 1 ? $buildUrl($page - 1) : null, 'label' => '&laquo; Previous', 'active' => false];
        for ($i = 1; $i <= $lastPage; $i++) {
            $links[] = ['url' => $buildUrl($i), 'label' => (string) $i, 'active' => $i === $page];
        }
        $links[] = ['url' => $page < $lastPage ? $buildUrl($page + 1) : null, 'label' => 'Next &raquo;', 'active' => false];

        return [
            'data' => $sliced->toArray(),
            'links' => [
                'first' => $buildUrl(1),
                'last' => $buildUrl($lastPage),
                'prev' => $page > 1 ? $buildUrl($page - 1) : null,
                'next' => $page < $lastPage ? $buildUrl($page + 1) : null,
            ],
            'meta' => [
                'current_page' => $page,
                'from' => $from,
                'last_page' => $lastPage,
                'links' => $links,
                'path' => $baseUrl,
                'per_page' => $perPage,
                'to' => $to,
                'total' => $total,
            ],
        ];
    }
}
