<?php

namespace Tests\Feature\Modules;

use App\Models\Category;
use Tests\TestCase;

class CategoriesModuleTest extends TestCase
{
    public function test_index_requires_permission(): void
    {
        $this->actingAsUser();
        $this->get(route('admin.categories.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('admin.categories.index'))->assertOk();
    }

    public function test_can_create_category(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.categories.store'), [
            'name' => 'Travel',
            'slug' => 'travel',
            'description' => 'Travel posts',
        ])->assertRedirect();

        $this->assertDatabaseHas('categories', ['slug' => 'travel']);
    }

    public function test_can_delete_category(): void
    {
        $this->actingAsSuperAdmin();
        $category = Category::factory()->create();

        $this->delete(route('admin.categories.destroy', $category))->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }
}
