<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_permission_seeder_creates_all_roles(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $expectedRoles = ['Super Admin', 'Admin', 'Store Manager', 'SEO Manager', 'Order Manager', 'Content Editor', 'Customer Support'];

        foreach ($expectedRoles as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_super_admin_bypasses_all_gates(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        // Any random permission should return true.
        $this->assertTrue($user->hasRole('Super Admin'));
        $this->assertTrue($user->can('manage products'));
        $this->assertTrue($user->can('manage security'));
    }

    public function test_seo_manager_edits_content_and_products_but_not_ai_or_settings(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('SEO Manager');

        // Store owner's rule: SEO people edit SEO, content AND products…
        $this->assertTrue($user->can('manage seo'));
        $this->assertTrue($user->can('manage content'));
        $this->assertTrue($user->can('manage products'));

        // …but never AI batches/settings, general settings, or security.
        $this->assertFalse($user->can('access_ai_product_batches'));
        $this->assertFalse($user->can('access_ai_settings'));
        $this->assertFalse($user->can('access_general_settings'));
        $this->assertFalse($user->can('manage security'));
    }
}
