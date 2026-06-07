<?php

namespace Tests\Feature\Modules;

use App\Models\EmailTemplate;
use App\Models\User;
use Tests\TestCase;

class EmailTemplatesModuleTest extends TestCase
{
    public function test_index_requires_permission(): void
    {
        $this->actingAsUser();
        $this->get(route('admin.email-templates.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('admin.email-templates.index'))->assertOk();
    }

    public function test_can_create_template_and_sets_owner(): void
    {
        $admin = $this->actingAsSuperAdmin();

        $this->post(route('admin.email-templates.store'), [
            'name' => 'Welcome',
            'slug' => 'welcome-test',
            'subject' => 'Hi {{name}}',
            'body_html' => '<p>Welcome {{name}}</p>',
        ])->assertRedirect(route('admin.email-templates.index'));

        $this->assertDatabaseHas('email_templates', [
            'slug' => 'welcome-test',
            'created_by' => $admin->id,
        ]);
    }

    public function test_non_super_admin_cannot_edit_others_template(): void
    {
        $owner = User::factory()->create();
        $template = EmailTemplate::factory()->create(['created_by' => $owner->id]);

        // A different user with email permissions still can't touch a template they don't own.
        $this->actingAsRole('manager');

        $this->get(route('admin.email-templates.edit', $template))->assertForbidden();
    }
}
