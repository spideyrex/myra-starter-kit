# PHP Backend

Server-side patterns used by the admin framework.

---

## Controller Pattern

Admin controllers follow a consistent structure with constructor-injected services.

**Location:** `app/Http/Controllers/Admin/`

### Standard CRUD Controller

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Products/Index', [
            'products' => $this->productService->list($request),
            'filters' => $request->only(['search', 'sort', 'direction']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $this->productService->create($validated);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $this->productService->update($product, $validated);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->productService->delete($product);

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }
}
```

### Key Conventions

- Constructor injection for services (`private readonly`)
- Return `Inertia::render()` for page responses
- Return `redirect()->route()->with('success', ...)` for mutations
- Pass `filters` to index pages for DataTable search/sort state
- Use Laravel route model binding for edit/update/destroy

---

## Service Pattern

Services encapsulate business logic and use the `SearchableQuery` trait for list operations.

**Location:** `app/Services/`

### Standard Service

```php
<?php

namespace App\Services;

use App\Admin\Traits\SearchableQuery;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ProductService
{
    use SearchableQuery;

    public function list(Request $request): LengthAwarePaginator
    {
        return $this->applySearchAndPaginate(
            Product::query(),
            $request,
            searchable: ['name'],
        );
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
```

### Advanced List Example

```php
public function list(Request $request): LengthAwarePaginator
{
    return $this->applySearchAndPaginate(
        Product::query()
            ->with(['category', 'tags'])
            ->when($request->category, fn ($q, $cat) => $q->where('category_id', $cat))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->min_price, fn ($q, $min) => $q->where('price', '>=', $min)),
        $request,
        searchable: ['name', 'sku', 'description'],
        defaultSort: 'name',
        defaultDir: 'asc',
        perPage: 25,
    );
}
```

---

## SearchableQuery Trait

Reusable trait that applies search, sort, and pagination to Eloquent queries.

**Location:** `app/Admin/Traits/SearchableQuery.php`

### Method Signature

```php
public function applySearchAndPaginate(
    Builder $query,
    Request $request,
    array $searchable = [],
    string $defaultSort = 'created_at',
    string $defaultDir = 'desc',
    int $perPage = 15,
): LengthAwarePaginator
```

### Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `$query` | `Builder` | required | Eloquent query builder |
| `$request` | `Request` | required | HTTP request (reads `search`, `sort`, `direction`, `per_page`) |
| `$searchable` | `array` | `[]` | Columns to search with `LIKE %query%` |
| `$defaultSort` | `string` | `'created_at'` | Default sort column |
| `$defaultDir` | `string` | `'desc'` | Default sort direction |
| `$perPage` | `int` | `15` | Items per page |

### How It Works

1. **Search:** If `$request->search` exists and `$searchable` is not empty, applies `WHERE (col1 LIKE %search% OR col2 LIKE %search% OR ...)`
2. **Sort:** If `$request->sort` exists, applies `ORDER BY {sort} {direction}`. Otherwise uses `$defaultSort` and `$defaultDir`
3. **Paginate:** Calls `->paginate($perPage)->withQueryString()` to preserve query params in pagination links

### Request Query Parameters

The trait reads these from the request (sent automatically by the DataTable component):

| Parameter | Description | Example |
|-----------|-------------|---------|
| `search` | Search query string | `?search=widget` |
| `sort` | Column to sort by | `?sort=name` |
| `direction` | Sort direction (`asc` or `desc`) | `?direction=asc` |
| `per_page` | Items per page override | `?per_page=25` |

---

## Route Conventions

Admin routes use a consistent pattern with permission middleware:

```php
// routes/web.php

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {

    // CRUD Resource
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('/products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.edit')->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');

    // Single Page
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
});
```

### Permission Naming

Permissions follow the pattern `{resource}.{action}`:

| Permission | Purpose |
|------------|---------|
| `products.view` | View product list |
| `products.create` | Create new products |
| `products.edit` | Edit existing products |
| `products.delete` | Delete products |

---

## Flash Messages

Controllers flash success/error messages that are automatically shown as toasts via `useFlashToasts`:

```php
// Success (green toast)
return redirect()->route('...')->with('success', 'Product created successfully.');

// Error (red toast)
return redirect()->back()->with('error', 'Something went wrong.');

// Warning (yellow toast)
return redirect()->back()->with('warning', 'Product is low on stock.');

// Info (blue toast)
return redirect()->back()->with('info', 'Product was archived.');
```
