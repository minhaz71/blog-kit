<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function enableMaintenance(): void
    {
        Setting::set('general.maintenance_mode', true);
        Cache::forget('settings.general');
    }

    public function test_guest_frontend_gets_maintenance_page(): void
    {
        $this->enableMaintenance();

        $this->get('/')->assertStatus(503);
    }

    public function test_logged_in_customer_still_sees_maintenance_but_staff_does_not(): void
    {
        $this->enableMaintenance();

        // A customer (no roles) is NOT staff → still sees the notice.
        $customer = \App\Models\User::create([
            'name' => 'Cust', 'email' => 'c@example.com', 'password' => bcrypt('secret1234'),
        ]);
        $this->actingAs($customer)->get('/')->assertStatus(503);

        // A staff member (has a role) previews the real site → not 503.
        $staff = \App\Models\User::create([
            'name' => 'Staff', 'email' => 's@example.com', 'password' => bcrypt('secret1234'),
        ]);
        \Spatie\Permission\Models\Role::findOrCreate('editor', 'web');
        $staff->assignRole('editor');
        $this->actingAs($staff)->get('/')->assertStatus(200);
    }

    public function test_cli_command_toggles_maintenance(): void
    {
        $this->artisan('blogkit:maintenance on')->assertSuccessful();
        $this->assertTrue((bool) setting('general.maintenance_mode'));

        \Illuminate\Support\Facades\Cache::forget('settings.general');
        $this->artisan('blogkit:maintenance off')->assertSuccessful();
        \Illuminate\Support\Facades\Cache::forget('settings.general');
        $this->assertFalse((bool) setting('general.maintenance_mode'));
    }

    public function test_admin_login_stays_reachable_during_maintenance(): void
    {
        $this->enableMaintenance();

        // Staff must be able to reach the admin login to sign in — never a 503.
        $response = $this->get('/admin/login');

        $this->assertNotSame(503, $response->getStatusCode());
    }

    public function test_maintenance_page_is_never_cacheable(): void
    {
        $this->enableMaintenance();

        // Without no-store, an edge cache (LiteSpeed/Cloudflare) could store
        // the maintenance page and keep serving it to logged-in staff.
        $this->get('/')
            ->assertStatus(503)
            ->assertHeader('X-LiteSpeed-Cache-Control', 'no-cache')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    public function test_undecryptable_two_factor_secret_self_heals_at_the_challenge(): void
    {
        // A restored database on a NEW APP_KEY: the stored 2FA secret can no
        // longer decrypt, which used to throw and lock the account out of
        // every page (EnforceTwoFactor redirects everything to the challenge).
        $user = \App\Models\User::create([
            'name' => 'Locked Admin',
            'email' => 'locked@example.com',
            'password' => bcrypt('secret1234'),
        ]);
        $user->forceFill([
            'two_factor_secret' => 'garbage-not-encrypted-with-this-key',
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->actingAs($user)->post(route('two-factor.verify'), ['code' => '123456']);

        // Self-heal: dead 2FA cleared, session verified, user let through.
        $response->assertRedirect();
        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => '2fa_reset_undecryptable', 'user_id' => $user->id]);
    }
}
