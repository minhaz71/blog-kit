<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    // ── Mass-assignment guards on User ────────────────────────────────

    public function test_user_auth_internals_are_not_mass_assignable(): void
    {
        // A careless create() with attacker-controlled keys must not verify
        // the email, forge login history, or set the public author slug.
        $user = User::create([
            'name' => 'Mallory',
            'email' => 'm@example.com',
            'password' => 'secret-password',
            'email_verified_at' => now(),
            'last_login_at' => now(),
            'public_slug' => 'chosen-slug',
        ]);

        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->last_login_at);
        $this->assertNotSame('chosen-slug', $user->public_slug); // auto-random instead
        $this->assertSame(12, strlen($user->public_slug));

        // But legitimate fields (admin form sets these) still fill.
        $this->assertSame('Mallory', $user->name);
    }

    // ── Destructive migrations blocked in EVERY environment ───────────

    public function test_destructive_migration_is_blocked_everywhere(): void
    {
        $guard = \App\Support\DatabaseSafetyGuard::class;

        // Hard-blocked without the explicit override — in ANY environment.
        $this->assertTrue($guard::mustHardBlock('migrate:fresh', override: false));
        $this->assertTrue($guard::mustHardBlock('db:wipe', override: false));
        $this->assertTrue($guard::mustHardBlock('migrate:rollback', override: false));
        $this->assertStringContainsString('Refused', $guard::hardBlockMessage('migrate:fresh'));

        // Allowed only with the deliberate opt-in; a normal additive migrate is
        // never blocked.
        $this->assertFalse($guard::mustHardBlock('migrate:fresh', override: true));
        $this->assertFalse($guard::mustHardBlock('migrate', override: false));
    }

    public function test_in_memory_test_db_is_treated_as_disposable(): void
    {
        // The suite runs on sqlite :memory:, so the guard must recognise it as
        // disposable (otherwise every RefreshDatabase run would be blocked).
        $this->assertTrue(\App\Support\DatabaseSafetyGuard::isDisposableDatabase());
    }

    // ── Custom-code editor gated to Super Admin ───────────────────────

    public function test_custom_code_tab_is_super_admin_only(): void
    {
        $this->seed();

        // Editing a page: a non-super-admin must NOT see the raw HTML/JS field.
        $page = \App\Models\Page::create(['title' => 'T', 'slug' => 'harden-t', 'content' => '<p>x</p>', 'status' => 'published']);

        $editor = User::factory()->create(['is_active' => true]);
        $editor->assignRole('Content Editor');
        $this->actingAs($editor)->get("/admin/pages/{$page->id}/edit")
            ->assertOk()
            ->assertDontSee('Custom JavaScript');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->get("/admin/pages/{$page->id}/edit")
            ->assertOk()
            ->assertSee('Custom JavaScript');
    }
}
