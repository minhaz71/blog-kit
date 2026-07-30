<?php

namespace Tests\Feature;

use App\Filament\Pages\AiSettings;
use App\Filament\Pages\GeneralSettings;
use App\Filament\Resources\AiImportBatchResource;
use App\Filament\Resources\PostResource;
use App\Filament\Resources\ProductResource;
use App\Models\User;
use App\Support\AdminAccess;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    /** Log in a user with a fresh role granting exactly the given access_* keys. */
    protected function userWithScreens(array $accessKeys): User
    {
        $role = Role::create(['name' => 'Custom '.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions(AdminAccess::expand($accessKeys));

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    public function test_content_product_role_reaches_content_and_products_but_not_ai_or_settings(): void
    {
        $this->actingAs($this->userWithScreens(['access_products', 'access_posts']));

        // Granted screens.
        $this->assertTrue(ProductResource::canAccess());
        $this->assertTrue(PostResource::canAccess());

        // Denied screens — the store owner's rule.
        $this->assertFalse(AiImportBatchResource::canAccess());
        $this->assertFalse(AiSettings::canAccess());
        $this->assertFalse(GeneralSettings::canAccess());
    }

    public function test_denied_screen_returns_403_on_direct_url(): void
    {
        $this->actingAs($this->userWithScreens(['access_products']));

        $this->get('/admin/general-settings')->assertForbidden();
        $this->get('/admin/ai-settings')->assertForbidden();
        // Granted one is reachable.
        $this->get('/admin/products')->assertOk();
    }

    public function test_granted_role_can_actually_edit_products(): void
    {
        // access_products implies "manage products", so the ProductPolicy
        // lets this role create/edit — not just view.
        $user = $this->userWithScreens(['access_products']);
        $this->assertTrue($user->can('manage products'));
        $this->assertTrue(\App\Models\Product::class && $user->can('create', \App\Models\Product::class));
    }

    public function test_super_admin_reaches_everything(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);

        $this->assertTrue(AiSettings::canAccess());
        $this->assertTrue(GeneralSettings::canAccess());
        $this->assertTrue(AiImportBatchResource::canAccess());
        $this->get('/admin/ai-settings')->assertOk();
    }

    public function test_role_matrix_persists_and_enforces_selected_screens(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        // Create a role via the matrix UI: only the Products + Blog posts boxes.
        Livewire::test(\App\Filament\Resources\RoleResource\Pages\CreateRole::class)
            ->fillForm([
                'name' => 'SEO Editor',
                'perms_catalog' => ['access_products'],
                'perms_content' => ['access_posts'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::where('name', 'SEO Editor')->first();
        $this->assertNotNull($role);
        // Stored the ticked screens + their implied action permissions.
        $this->assertTrue($role->hasPermissionTo('access_products'));
        $this->assertTrue($role->hasPermissionTo('access_posts'));
        $this->assertTrue($role->hasPermissionTo('manage products'));
        $this->assertFalse($role->hasPermissionTo('access_ai_settings'));

        // A user with that role is enforced accordingly.
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);
        $this->assertTrue(ProductResource::canAccess());
        $this->assertFalse(AiSettings::canAccess());
    }
}
