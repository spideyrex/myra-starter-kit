<?php

namespace App\Admin\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Sample-data provider for pages scaffolded by `make:myra-component`.
 *
 * Each `for{Component}()` method returns the exact prop array its cloned demo
 * page expects, so generated pages render with realistic mock data out of the
 * box. Replace these calls with real queries once you wire up your model.
 *
 * Ported from App\Http\Controllers\Admin\DemoController.
 */
trait HasSampleData
{
    private function seedFaker(int $seed = 42): void
    {
        fake()->seed($seed);
    }

    // --- Prop providers (one per data-driven component) -----------------------

    public function forBulkActions(Request $request): array
    {
        return [
            'products' => $this->paginateCollection($this->sampleProducts(), $request, ['category', 'status']),
            'filters' => (object) $request->only('search', 'sort', 'direction', 'category', 'status'),
        ];
    }

    public function forInlineEditing(Request $request): array
    {
        $products = $this->sampleProducts()->map(function ($item) {
            $item['is_active'] = $item['status'] === 'active';
            return $item;
        });

        return [
            'products' => $this->paginateCollection($products, $request, ['category', 'status']),
            'filters' => (object) $request->only('search', 'sort', 'direction', 'category', 'status'),
        ];
    }

    public function forAdvancedFilters(Request $request): array
    {
        $products = $this->sampleProducts();

        if ($search = $request->get('search')) {
            $searchLower = strtolower($search);
            $products = $products->filter(fn ($p) => collect($p)->contains(fn ($val) => is_string($val) && str_contains(strtolower($val), $searchLower)));
        }
        if ($category = $request->get('category')) {
            $products = $products->filter(fn ($p) => $p['category'] === $category);
        }
        if ($status = $request->get('status')) {
            $products = $products->filter(fn ($p) => $p['status'] === $status);
        }
        if ($request->filled('in_stock')) {
            $inStock = $request->get('in_stock');
            if ($inStock === '1') {
                $products = $products->filter(fn ($p) => $p['stock'] > 0);
            } elseif ($inStock === '0') {
                $products = $products->filter(fn ($p) => $p['stock'] === 0);
            }
        }
        if ($request->get('high_value') === '1') {
            $products = $products->filter(fn ($p) => $p['price'] >= 500);
        }
        if ($request->filled('created_from')) {
            $products = $products->filter(fn ($p) => $p['created_at'] >= $request->input('created_from'));
        }
        if ($request->filled('created_to')) {
            $products = $products->filter(fn ($p) => $p['created_at'] <= $request->input('created_to'));
        }

        $filterKeys = ['search', 'sort', 'direction', 'category', 'status', 'in_stock', 'high_value', 'created_from', 'created_to', 'query_builder'];

        return [
            'products' => $this->manualPaginate($products->values(), $request),
            'filters' => (object) $request->only($filterKeys),
        ];
    }

    public function forGrouping(Request $request): array
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
            'created_at' => now()->subDays($i * 2)->toDateTimeString(),
        ]);

        return [
            'orders' => $this->paginateCollection($orders, $request, ['status'], 40),
            'filters' => (object) $request->only('search', 'sort', 'direction', 'status'),
        ];
    }

    public function forImportExport(Request $request): array
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
            $contacts = $contacts->filter(fn ($item) => str_contains(strtolower($item['name']), strtolower($search)) || str_contains(strtolower($item['email']), strtolower($search)) || str_contains(strtolower($item['company']), strtolower($search)));
        }

        return [
            'contacts' => $this->manualPaginate($contacts, $request),
            'filters' => (object) $request->only('search', 'sort', 'direction'),
        ];
    }

    public function forActionModals(Request $request): array
    {
        $this->seedFaker(200);
        $assignees = collect(range(1, 30))->map(fn () => fake()->name());

        $items = collect(range(1, 30))->map(fn ($i) => [
            'id' => $i,
            'title' => fake()->sentence(4),
            'description' => fake()->sentence(8),
            'priority' => ['low', 'medium', 'high'][($i - 1) % 3],
            'assignee' => $assignees[$i - 1],
            'status' => ['open', 'in_progress', 'completed'][($i - 1) % 3],
            'created_at' => now()->subDays($i * 3)->toDateTimeString(),
        ]);

        if ($search = $request->get('search')) {
            $items = $items->filter(fn ($item) => str_contains(strtolower($item['title']), strtolower($search)) || str_contains(strtolower($item['assignee']), strtolower($search)));
        }

        return [
            'tasks' => $this->manualPaginate($items, $request),
            'filters' => (object) $request->only('search', 'sort', 'direction'),
        ];
    }

    public function forSoftDeletes(Request $request): array
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
            $items = $items->filter(fn ($item) => str_contains(strtolower($item['name']), strtolower($search)) || str_contains(strtolower($item['email']), strtolower($search)));
        }

        return [
            'users' => $this->manualPaginate($items, $request),
            'filters' => (object) $request->only('search', 'sort', 'direction', 'trashed'),
        ];
    }

    public function forInfolist(): array
    {
        return [
            'user' => [
                'id' => 1,
                'name' => 'Jane Cooper',
                'email' => 'jane.cooper@example.com',
                'avatar' => null,
                'status' => 'active',
                'role' => 'admin',
                'phone' => '+1 (555) 123-4567',
                'company' => 'Acme Corp',
                'bio' => 'Senior software engineer with 10+ years of experience building scalable web applications.',
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
            ],
        ];
    }

    public function forRelationManager(Request $request): array
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

        $actRequest = clone $request;
        $actRequest->merge([
            'sort' => $request->get('act_sort', 'id'),
            'direction' => $request->get('act_direction', 'asc'),
        ]);

        return [
            'user' => $user,
            'orders' => $this->manualPaginate($orders, $request),
            'activities' => $this->manualPaginate($activities, $actRequest, 10, 'act_page'),
            'filters' => (object) $request->only('search', 'sort', 'direction', 'act_search', 'act_sort', 'act_direction'),
        ];
    }

    public function forWidgets(): array
    {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
        $salesData = [65, 78, 52, 91, 84, 107];
        $revenueData = [12400, 15800, 9200, 18600, 16100, 19800];

        return [
            'data' => [
                'totalUsers' => 1284,
                'revenue' => 45230,
                'orders' => 356,
                'conversionRate' => 3.2,
                'salesByMonth' => collect($months)->map(fn ($m, $i) => ['month' => $m, 'sales' => $salesData[$i], 'revenue' => $revenueData[$i]])->toArray(),
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
                ],
            ],
        ];
    }

    public function forReordering(): array
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

        return ['tasks' => $tasks];
    }

    // --- Shared helpers -------------------------------------------------------

    private function sampleProducts(): Collection
    {
        $this->seedFaker(50);
        $categories = ['Electronics', 'Clothing', 'Books', 'Home & Garden'];
        $statuses = ['active', 'draft', 'archived'];
        $priceTiers = [9.99, 14.50, 24.99, 39.95, 49.99, 79.00, 99.99, 149.95, 199.00, 249.50,
                       299.99, 349.00, 399.95, 449.99, 499.00, 599.99, 699.00, 799.95, 999.99, 1299.00];
        $names = collect(range(1, 50))->map(fn () => fake()->words(3, true));

        return collect(range(1, 50))->map(fn ($i) => [
            'id' => $i,
            'name' => $names[$i - 1],
            'category' => $categories[($i - 1) % 4],
            'price' => $priceTiers[($i - 1) % count($priceTiers)],
            'stock' => $i % 7 === 0 ? 0 : (($i * 23) % 500) + 1,
            'status' => $statuses[($i - 1) % 3],
            'created_at' => now()->subDays($i * 7)->toDateTimeString(),
        ]);
    }

    private function paginateCollection(Collection $items, Request $request, array $filterKeys = [], int $perPage = 10): array
    {
        if ($search = $request->get('search')) {
            $searchLower = strtolower($search);
            $items = $items->filter(fn ($item) => collect($item)->contains(fn ($val) => is_string($val) && str_contains(strtolower($val), $searchLower)));
        }
        foreach ($filterKeys as $key) {
            if ($value = $request->get($key)) {
                $items = $items->filter(fn ($item) => ($item[$key] ?? '') === $value);
            }
        }

        return $this->manualPaginate($items, $request, $perPage);
    }

    private function manualPaginate(Collection $items, Request $request, int $perPage = 10, string $pageName = 'page'): array
    {
        $sort = $request->get('sort', 'id');
        $direction = $request->get('direction', 'asc');
        $items = $direction === 'desc' ? $items->sortByDesc($sort) : $items->sortBy($sort);

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

    // >>> MYRA v2.2 [B] START
    public function forSavedViews(Request $request): array
    {
        $user = $request->user();
        $tableKey = 'admin.demo.saved-views';

        return [
            'products' => $this->paginateCollection($this->sampleProducts(), $request, ['category', 'status']),
            'filters' => (object) $request->only('search', 'sort', 'direction', 'category', 'status', 'per_page'),
            'savedViews' => \App\Models\TableView::visibleTo($user)
                ->where('table_key', $tableKey)
                ->where('name', '!=', \App\Models\TableView::COLUMNS_NAME)
                ->orderBy('sort')
                ->orderBy('name')
                ->get()
                ->map(fn (\App\Models\TableView $view) => $view->toClientArray($user))
                ->values(),
            'canShareViews' => (bool) $user->current_team_id,
        ];
    }
    // <<< MYRA v2.2 [B] END
}
