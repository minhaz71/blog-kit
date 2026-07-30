<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Backups download over a plain authenticated GET (streaming), not a Livewire
 * action — so large archives download instead of hanging the spinner. Still
 * gated by the Backups-screen permission.
 */
class BackupDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBackupFile(): Backup
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $rel = 'backups/test-'.uniqid().'.zip';
        file_put_contents(storage_path('app/'.$rel), 'PK'.str_repeat('x', 128));

        return Backup::create(['path' => $rel, 'status' => 'completed', 'size' => 130, 'type' => 'full']);
    }

    protected function tearDownBackup(Backup $b): void
    {
        @unlink(storage_path('app/'.$b->path));
    }

    public function test_admin_can_stream_download_a_completed_backup(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');
        $backup = $this->makeBackupFile();

        $res = $this->actingAs($admin)->get(route('admin.backups.download', $backup));
        $res->assertOk();
        $this->assertStringContainsString('attachment', (string) $res->headers->get('content-disposition'));

        $this->tearDownBackup($backup);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $backup = $this->makeBackupFile();

        $this->get(route('admin.backups.download', $backup))->assertRedirect();

        $this->tearDownBackup($backup);
    }

    public function test_customer_without_permission_gets_403(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $customer = User::factory()->create(['is_active' => true]); // no admin role
        $backup = $this->makeBackupFile();

        $this->actingAs($customer)->get(route('admin.backups.download', $backup))->assertForbidden();

        $this->tearDownBackup($backup);
    }

    public function test_missing_file_returns_404(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');
        $backup = Backup::create(['path' => 'backups/does-not-exist.zip', 'status' => 'completed', 'size' => 1, 'type' => 'full']);

        $this->actingAs($admin)->get(route('admin.backups.download', $backup))->assertNotFound();
    }
}
