<?php

namespace Tests\Feature\Modules;

use App\Models\Page;
use Tests\TestCase;

class PagesModuleTest extends TestCase
{
    public function test_index_requires_permission(): void
    {
        $this->actingAsUser();
        $this->get(route('admin.pages.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('admin.pages.index'))->assertOk();
    }

    public function test_can_create_page(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.pages.store'), [
            'title' => 'About Us',
            'slug' => 'about-us',
            'body_html' => '<p>Company info</p>',
            'status' => 'published',
            'is_public' => true,
        ])->assertRedirect(route('admin.pages.index'));

        $this->assertDatabaseHas('pages', ['slug' => 'about-us']);
    }

    public function test_can_delete_page(): void
    {
        $this->actingAsSuperAdmin();
        $page = Page::factory()->create();

        $this->delete(route('admin.pages.destroy', $page))->assertRedirect();

        $this->assertSoftDeleted('pages', ['id' => $page->id]);
    }
}
