<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(): User
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_purge_and_cloudflare_buttons_render_in_the_header(): void
    {
        $page = $this->actingAs($this->staff())
            ->get('/admin/performance-settings')
            ->assertOk();

        $page->assertSee('Purge All')
            ->assertSee('Check Cloudflare connection')
            ->assertSee('Guest page cache')
            ->assertSee('Critical CSS');

        // Optimize only shows once Cloudflare is connected.
        $page->assertDontSee('Optimize Cloudflare');
    }

    public function test_optimize_button_appears_once_cloudflare_is_connected(): void
    {
        Setting::set('performance.cloudflare_email', 'owner@example.com');
        Setting::set('performance.cloudflare_api_key', 'key');
        Setting::set('performance.cloudflare_domain', 'example.com');
        Setting::set('performance.cloudflare_zone_id', 'zone-abc');

        $this->actingAs($this->staff())
            ->get('/admin/performance-settings')
            ->assertOk()
            ->assertSee('Optimize Cloudflare');
    }
}
