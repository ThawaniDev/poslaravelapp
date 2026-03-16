<?php

namespace Tests\Feature\Admin;

use App\Domain\AdminPanel\Models\AdminUser;
use App\Domain\ProviderRegistration\Models\ProviderPermission;
use App\Domain\StaffManagement\Models\DefaultRoleTemplate;
use App\Domain\StaffManagement\Models\DefaultRoleTemplatePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProviderRolePermissionApiTest extends TestCase
{
    use RefreshDatabase;

    private string $prefix = '/api/v2/admin/provider-roles';

    protected function setUp(): void
    {
        parent::setUp();

        $admin = AdminUser::forceCreate([
            'id'            => Str::uuid(),
            'name'          => 'Admin',
            'email'         => 'admin@test.com',
            'password_hash' => bcrypt('password'),
            'is_active'     => true,
        ]);

        Sanctum::actingAs($admin, ['*'], 'admin-api');
    }

    private function createPermission(string $name = 'manage_products', string $group = 'catalog'): ProviderPermission
    {
        return ProviderPermission::forceCreate([
            'id'        => Str::uuid(),
            'name'      => $name,
            'group'     => $group,
            'is_active' => true,
        ]);
    }

    private function createTemplate(string $name = 'Manager', string $slug = 'manager'): DefaultRoleTemplate
    {
        return DefaultRoleTemplate::forceCreate([
            'id'   => Str::uuid(),
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // PROVIDER PERMISSIONS
    // ────────────────────────────────────────────────────────────
    public function test_permissions_returns_paginated(): void
    {
        $this->createPermission('manage_products', 'catalog');
        $this->createPermission('view_reports', 'reports');

        $resp = $this->getJson("{$this->prefix}/permissions");
        $resp->assertOk()
             ->assertJsonPath('data.total', 2);
    }

    public function test_permissions_filters_by_group(): void
    {
        $this->createPermission('manage_products', 'catalog');
        $this->createPermission('view_reports', 'reports');

        $resp = $this->getJson("{$this->prefix}/permissions?group=catalog");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_permissions_filters_by_active(): void
    {
        $this->createPermission('manage_products', 'catalog');
        ProviderPermission::forceCreate([
            'id' => Str::uuid(), 'name' => 'legacy_perm',
            'group' => 'catalog', 'is_active' => false,
        ]);

        $resp = $this->getJson("{$this->prefix}/permissions?is_active=true");
        $resp->assertOk()
             ->assertJsonPath('data.total', 1);
    }

    public function test_permissions_empty(): void
    {
        $resp = $this->getJson("{$this->prefix}/permissions");
        $resp->assertOk()
             ->assertJsonPath('data.total', 0);
    }

    // ────────────────────────────────────────────────────────────
    // DEFAULT ROLE TEMPLATES — LIST
    // ────────────────────────────────────────────────────────────
    public function test_templates_returns_paginated(): void
    {
        $this->createTemplate('Owner', 'owner');
        $this->createTemplate('Manager', 'manager');

        $resp = $this->getJson("{$this->prefix}/templates");
        $resp->assertOk()
             ->assertJsonPath('data.total', 2);
    }

    public function test_templates_empty(): void
    {
        $resp = $this->getJson("{$this->prefix}/templates");
        $resp->assertOk()
             ->assertJsonPath('data.total', 0);
    }

    // ────────────────────────────────────────────────────────────
    // DEFAULT ROLE TEMPLATES — SHOW
    // ────────────────────────────────────────────────────────────
    public function test_show_template(): void
    {
        $template = $this->createTemplate();

        $resp = $this->getJson("{$this->prefix}/templates/{$template->id}");
        $resp->assertOk()
             ->assertJsonPath('data.slug', 'manager');
    }

    public function test_show_template_includes_permissions(): void
    {
        $template = $this->createTemplate();
        $perm = $this->createPermission();

        DefaultRoleTemplatePermission::forceCreate([
            'id' => Str::uuid(),
            'default_role_template_id' => $template->id,
            'provider_permission_id' => $perm->id,
        ]);

        $resp = $this->getJson("{$this->prefix}/templates/{$template->id}");
        $resp->assertOk()
             ->assertJsonPath('data.default_role_template_permissions.0.provider_permission.name', 'manage_products');
    }

    public function test_show_template_not_found(): void
    {
        $resp = $this->getJson("{$this->prefix}/templates/" . Str::uuid());
        $resp->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // DEFAULT ROLE TEMPLATES — CREATE
    // ────────────────────────────────────────────────────────────
    public function test_create_template(): void
    {
        $resp = $this->postJson("{$this->prefix}/templates", [
            'name'        => 'Cashier',
            'name_ar'     => 'أمين صندوق',
            'slug'        => 'cashier',
            'description' => 'Cashier role template',
        ]);

        $resp->assertCreated()
             ->assertJsonPath('data.slug', 'cashier')
             ->assertJsonPath('data.name_ar', 'أمين صندوق');
    }

    public function test_create_template_requires_name_and_slug(): void
    {
        $resp = $this->postJson("{$this->prefix}/templates", []);
        $resp->assertUnprocessable()
             ->assertJsonValidationErrors(['name', 'slug']);
    }

    public function test_create_template_slug_must_be_unique(): void
    {
        $this->createTemplate('Manager', 'manager');

        $resp = $this->postJson("{$this->prefix}/templates", [
            'name' => 'Another Manager',
            'slug' => 'manager',
        ]);
        $resp->assertUnprocessable()
             ->assertJsonValidationErrors(['slug']);
    }

    // ────────────────────────────────────────────────────────────
    // DEFAULT ROLE TEMPLATES — UPDATE
    // ────────────────────────────────────────────────────────────
    public function test_update_template(): void
    {
        $template = $this->createTemplate();

        $resp = $this->putJson("{$this->prefix}/templates/{$template->id}", [
            'name'        => 'Senior Manager',
            'description' => 'Updated description',
        ]);

        $resp->assertOk()
             ->assertJsonPath('data.name', 'Senior Manager');
    }

    public function test_update_template_not_found(): void
    {
        $resp = $this->putJson("{$this->prefix}/templates/" . Str::uuid(), [
            'name' => 'Test',
        ]);
        $resp->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // DEFAULT ROLE TEMPLATES — DELETE
    // ────────────────────────────────────────────────────────────
    public function test_delete_template(): void
    {
        $template = $this->createTemplate();

        $resp = $this->deleteJson("{$this->prefix}/templates/{$template->id}");
        $resp->assertOk();

        $this->assertDatabaseMissing('default_role_templates', ['id' => $template->id]);
    }

    public function test_delete_template_cascades_permissions(): void
    {
        $template = $this->createTemplate();
        $perm = $this->createPermission();

        $tp = DefaultRoleTemplatePermission::forceCreate([
            'id' => Str::uuid(),
            'default_role_template_id' => $template->id,
            'provider_permission_id' => $perm->id,
        ]);

        $resp = $this->deleteJson("{$this->prefix}/templates/{$template->id}");
        $resp->assertOk();

        $this->assertDatabaseMissing('default_role_template_permissions', ['id' => $tp->id]);
    }

    public function test_delete_template_not_found(): void
    {
        $resp = $this->deleteJson("{$this->prefix}/templates/" . Str::uuid());
        $resp->assertNotFound();
    }

    // ────────────────────────────────────────────────────────────
    // TEMPLATE PERMISSION ASSIGNMENT
    // ────────────────────────────────────────────────────────────
    public function test_get_template_permissions(): void
    {
        $template = $this->createTemplate();
        $perm1 = $this->createPermission('manage_products', 'catalog');
        $perm2 = $this->createPermission('view_reports', 'reports');

        DefaultRoleTemplatePermission::forceCreate([
            'id' => Str::uuid(),
            'default_role_template_id' => $template->id,
            'provider_permission_id' => $perm1->id,
        ]);

        $resp = $this->getJson("{$this->prefix}/templates/{$template->id}/permissions");
        $resp->assertOk()
             ->assertJsonCount(1, 'data');
    }

    public function test_update_template_permissions_sync(): void
    {
        $template = $this->createTemplate();
        $perm1 = $this->createPermission('manage_products', 'catalog');
        $perm2 = $this->createPermission('view_reports', 'reports');
        $perm3 = $this->createPermission('manage_orders', 'orders');

        // Assign perm1 first
        DefaultRoleTemplatePermission::forceCreate([
            'id' => Str::uuid(),
            'default_role_template_id' => $template->id,
            'provider_permission_id' => $perm1->id,
        ]);

        // Sync to perm2 + perm3
        $resp = $this->putJson("{$this->prefix}/templates/{$template->id}/permissions", [
            'permission_ids' => [$perm2->id, $perm3->id],
        ]);

        $resp->assertOk()
             ->assertJsonCount(2, 'data');

        // perm1 should be gone
        $this->assertDatabaseMissing('default_role_template_permissions', [
            'default_role_template_id' => $template->id,
            'provider_permission_id' => $perm1->id,
        ]);
    }

    public function test_update_template_permissions_validates_ids(): void
    {
        $template = $this->createTemplate();

        $resp = $this->putJson("{$this->prefix}/templates/{$template->id}/permissions", []);
        $resp->assertUnprocessable()
             ->assertJsonValidationErrors(['permission_ids']);
    }

    public function test_update_template_permissions_validates_existing_ids(): void
    {
        $template = $this->createTemplate();

        $resp = $this->putJson("{$this->prefix}/templates/{$template->id}/permissions", [
            'permission_ids' => [Str::uuid()],
        ]);
        $resp->assertUnprocessable()
             ->assertJsonValidationErrors(['permission_ids.0']);
    }

    // ────────────────────────────────────────────────────────────
    // AUTH
    // ────────────────────────────────────────────────────────────
    public function test_unauthenticated_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $resp = $this->getJson("{$this->prefix}/permissions");
        $resp->assertUnauthorized();
    }
}
