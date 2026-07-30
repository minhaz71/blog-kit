<?php

namespace Tests\Feature;

use App\Filament\Pages\SettingsFinder;
use App\Support\SettingsCatalog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsFinderTest extends TestCase
{
    use RefreshDatabase;

    protected function superAdmin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_finds_settings_by_keyword_and_shows_location(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SettingsFinder::class)
            ->set('q', 'under construction')
            ->assertSee('Maintenance mode (site under construction)')
            ->assertSee('Site status');

        Livewire::test(SettingsFinder::class)
            ->set('q', 'noindex')
            ->assertSee('Discourage search engines');

        Livewire::test(SettingsFinder::class)
            ->set('q', 'permalink')
            ->assertSee('Permalinks');
    }

    public function test_menu_items_are_searchable_too(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(SettingsFinder::class)
            ->set('q', 'orders')
            ->assertSee('Orders');
    }

    public function test_results_respect_access_control(): void
    {
        $this->seed(RolePermissionSeeder::class);

        // A role that can only reach Products — no SEO/settings screens.
        $role = Role::firstOrCreate(['name' => 'Catalog Only', 'guard_name' => 'web']);
        $role->syncPermissions(['access_products']);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);

        $titles = collect(SettingsCatalog::search(''))->pluck('title');

        $this->assertTrue($titles->contains('Products'));
        $this->assertFalse($titles->contains('Permalinks — product / category / blog URL base'));
        $this->assertFalse($titles->contains('SEO settings'));
    }
}
