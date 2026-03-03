# Getting Started

## Creating a New CRUD Resource

The fastest way to add a new admin module is the scaffold command:

```bash
php artisan myra:resource Product
```

This generates 5 files:

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Admin/ProductController.php` | CRUD controller with injected service |
| `app/Services/ProductService.php` | Service with search, create, update, delete |
| `resources/js/Pages/Admin/Products/Index.vue` | List page with DataTable |
| `resources/js/Pages/Admin/Products/Create.vue` | Create form with FormFields |
| `resources/js/Pages/Admin/Products/Edit.vue` | Edit form with pre-filled data |

The command also prints route definitions to paste into `routes/web.php`:

```php
// Products
Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
Route::get('/products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.edit')->name('products.edit');
Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
```

## Creating a Single Page

For non-CRUD pages (dashboards, analytics, settings):

```bash
php artisan myra:page Analytics
```

This generates 2 files:

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Admin/AnalyticsController.php` | Controller with `index()` method |
| `resources/js/Pages/Admin/Analytics/Index.vue` | Basic page with PageHeader |

## After Scaffolding

### 1. Add the routes

Paste the printed route snippet into `routes/web.php` inside your admin route group.

### 2. Create the model (if needed)

```bash
php artisan make:model Product -m
```

### 3. Customize the form schema

Open the generated Create/Edit pages and modify the schema:

```ts
const schema = [
    TextInput.make('name').required(),
    TextInput.make('sku').required().placeholder('PRD-001'),
    TextInput.make('price').numeric().required(),
    Textarea.make('description').colSpan(2),
    Select.make('category').options({
        electronics: 'Electronics',
        clothing: 'Clothing',
        books: 'Books',
    }),
    Toggle.make('is_active').label('Active'),
];
```

### 4. Update the controller validation

Open the generated controller and update the validation rules in `store()` and `update()`:

```php
$validated = $request->validate([
    'name' => 'required|string|max:255',
    'sku' => 'required|string|unique:products,sku',
    'price' => 'required|numeric|min:0',
    'description' => 'nullable|string',
    'category' => 'required|in:electronics,clothing,books',
    'is_active' => 'boolean',
]);
```

### 5. Update the service

Customize searchable columns and add any business logic:

```php
public function list(Request $request): LengthAwarePaginator
{
    return $this->applySearchAndPaginate(
        Product::query()
            ->when($request->category, fn ($q, $cat) => $q->where('category', $cat)),
        $request,
        searchable: ['name', 'sku', 'description'],
    );
}
```

### 6. Customize the Index page columns

Update the `columns` array and add custom cell templates:

```ts
const columns: Column[] = [
    { key: 'name', label: 'Name', sortable: true },
    { key: 'sku', label: 'SKU', sortable: true },
    { key: 'price', label: 'Price', sortable: true },
    { key: 'category', label: 'Category' },
    { key: 'is_active', label: 'Status' },
    { key: 'created_at', label: 'Created', sortable: true },
];
```

```vue
<template #cell-price="{ value }">
    ${{ Number(value).toFixed(2) }}
</template>

<template #cell-is_active="{ value }">
    <StatusBadge :status="value ? 'active' : 'suspended'" />
</template>
```

## Typical Page Patterns

### CRUD Resource Page

```
Controller (index/create/store/edit/update/destroy)
    └── Service (list/create/update/delete + SearchableQuery)
        └── 3 Vue Pages
            ├── Index.vue  → DataTable + RowActions
            ├── Create.vue → ResourceForm + FormFields
            └── Edit.vue   → ResourceForm + FormFields
```

### Settings Page

```
Controller (index/update)
    └── Vue Page
        └── SettingsCard + FormFields (per section)
```

### Dashboard / Analytics Page

```
Controller (index)
    └── Vue Page
        └── StatCard + DataTable/Charts
```
