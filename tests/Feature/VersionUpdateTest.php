<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Preflight;
use App\Support\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Version::forget();
    }

    // ── Version service ───────────────────────────────────────────────

    public function test_version_service_reads_manifest_and_components(): void
    {
        $this->assertSame((string) config('shopkit.version'), Version::core());
        $this->assertNotSame('', Version::core());

        $components = Version::components();
        $this->assertArrayHasKey('ai-product-writer', $components);
        $this->assertArrayHasKey('ai-blog-writer', $components);
        $this->assertArrayHasKey('link-agent', $components);
        // Every component has a human label for the admin table.
        foreach (array_keys($components) as $slug) {
            $this->assertArrayHasKey($slug, Version::COMPONENT_LABELS);
        }
    }

    public function test_git_helpers_are_tolerant_when_not_a_repo(): void
    {
        // The test checkout is not a git repo — helpers must degrade to null,
        // never throw, so the admin page and updater stay safe.
        if (! Version::isGitRepo()) {
            $this->assertNull(Version::gitBranch());
            $this->assertNull(Version::gitCommit());
            $this->assertNull(Version::commitsBehind());
        }
        $this->assertTrue(true);
    }

    // ── Preflight ─────────────────────────────────────────────────────

    public function test_preflight_reports_checks_with_severities(): void
    {
        $summary = Preflight::summary();

        $this->assertArrayHasKey('ok', $summary);
        $keys = array_column($summary['checks'], 'key');
        foreach (['app_env', 'app_debug', 'unsafe_wipe', 'app_key', 'backups_writable'] as $k) {
            $this->assertContains($k, $keys);
        }

        $this->artisan('shopkit:preflight'); // must run without error (exit code varies by env)
    }

    // ── Updater guards ────────────────────────────────────────────────

    public function test_update_refuses_when_not_a_git_repo(): void
    {
        if (Version::isGitRepo()) {
            $this->markTestSkipped('This checkout is a git repo.');
        }

        $this->artisan('shopkit:update')
            ->expectsOutputToContain('not a git checkout')
            ->assertExitCode(1);
    }

    public function test_update_dry_run_changes_nothing(): void
    {
        if (! Version::isGitRepo()) {
            $this->markTestSkipped('Dry-run path needs a git repo to reach.');
        }

        // Should never enter maintenance mode or create a backup on a dry run.
        $this->artisan('shopkit:update', ['--dry-run' => true])->assertExitCode(0);
        $this->assertSame(0, \App\Models\Backup::count());
        $this->assertFalse(app()->isDownForMaintenance());
    }

    // ── Admin page ────────────────────────────────────────────────────

    public function test_updates_page_is_super_admin_only(): void
    {
        $this->seed();

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole('Order Manager');
        $this->actingAs($staff)->get('/admin/system-updates')->assertForbidden();

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->get('/admin/system-updates')
            ->assertOk()
            ->assertSee(Version::core())
            ->assertSee('Installed tools');
    }
}
