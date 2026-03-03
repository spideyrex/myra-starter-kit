# Scaffold Commands

Artisan commands for generating admin resources and pages.

---

## myra:resource

Generates a full CRUD resource with controller, service, and 3 Vue pages.

```bash
php artisan myra:resource {Name}
```

### Example

```bash
php artisan myra:resource Product
```

### Generated Files

| File | Description |
|------|-------------|
| `app/Http/Controllers/Admin/ProductController.php` | CRUD controller with constructor-injected `ProductService` |
| `app/Services/ProductService.php` | Service with `SearchableQuery` trait for list/create/update/delete |
| `resources/js/Pages/Admin/Products/Index.vue` | DataTable with schema-based columns and actions (no manual slots) |
| `resources/js/Pages/Admin/Products/Create.vue` | ResourceForm with Section layout and FormFields |
| `resources/js/Pages/Admin/Products/Edit.vue` | ResourceForm with Section layout and pre-filled data |

### Console Output

The command prints route definitions to paste into your `routes/web.php`:

```
// Products
Route::get('/products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
Route::get('/products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
Route::post('/products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.edit')->name('products.edit');
Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');
```

### Safety

- Skips files that already exist (prints a warning)
- Creates directories automatically if they don't exist

---

## myra:page

Generates a single admin page (for dashboards, analytics, settings).

```bash
php artisan myra:page {Name}
```

### Example

```bash
php artisan myra:page Analytics
```

### Generated Files

| File | Description |
|------|-------------|
| `app/Http/Controllers/Admin/AnalyticsController.php` | Controller with `index()` method |
| `resources/js/Pages/Admin/Analytics/Index.vue` | Basic page with PageHeader |

### Console Output

```
Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
```

---

## Variable Substitutions

Both commands use stub templates with placeholder replacement:

| Placeholder | Example (for `Product`) | Description |
|-------------|------------------------|-------------|
| `{{ model }}` | `Product` | PascalCase singular |
| `{{ modelVariable }}` | `product` | camelCase singular |
| `{{ modelPlural }}` | `Products` | PascalCase plural |
| `{{ modelPluralVariable }}` | `products` | camelCase plural |
| `{{ routePrefix }}` | `products` | kebab-case plural |
| `{{ permissionPrefix }}` | `products` | kebab-case plural |

### Pluralization Examples

| Input | Plural | Route Prefix |
|-------|--------|--------------|
| `Product` | `Products` | `products` |
| `Category` | `Categories` | `categories` |
| `Person` | `People` | `people` |
| `BlogPost` | `BlogPosts` | `blog-posts` |

Laravel's `Str::plural()` and `Str::kebab()` handle pluralization and casing.

---

## Stub Files

All templates are in `stubs/admin/`:

| Stub | Used by | Description |
|------|---------|-------------|
| `controller.resource.stub` | `myra:resource` | CRUD controller |
| `service.stub` | `myra:resource` | Service with SearchableQuery |
| `page.index.stub` | `myra:resource` | DataTable list page |
| `page.create.stub` | `myra:resource` | Create form page |
| `page.edit.stub` | `myra:resource` | Edit form page |
| `controller.page.stub` | `myra:page` | Single-action controller |
| `page.single.stub` | `myra:page` | Basic page template |

### Customizing Stubs

You can modify the stubs to match your project's conventions. For example, to add a `description` field to all generated resources, edit:

- `stubs/admin/page.create.stub` — Add to the schema array
- `stubs/admin/page.edit.stub` — Add to the schema array and props
- `stubs/admin/controller.resource.stub` — Add to validation rules
- `stubs/admin/service.stub` — Add to searchable columns if needed

---

## Post-Scaffold Checklist

After running `myra:resource`:

1. **Add routes** — Paste the printed routes into `routes/web.php`
2. **Create model** — `php artisan make:model {Name} -m` (if it doesn't exist)
3. **Run migration** — `php artisan migrate`
4. **Update validation** — Edit the controller's `store()` and `update()` rules
5. **Customize form schema** — Edit Create.vue and Edit.vue schemas
6. **Customize table columns** — Edit Index.vue column definitions
7. **Add searchable columns** — Edit the service's `list()` method
8. **Create permissions** — Add permissions via seeder or admin UI
9. **Add sidebar navigation** — Add the menu item to the sidebar config
