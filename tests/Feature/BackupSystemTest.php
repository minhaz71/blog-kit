<?php

namespace Tests\Feature;

use App\Support\BackupCompatibility;
use App\Support\BackupManifest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BackupSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/backups/backup-*.zip')) ?: [] as $f) {
            @unlink($f);
        }
        @unlink(storage_path('app/backups/test-archive.zip'));
        parent::tearDown();
    }

    /** Build a zip with a given manifest (and optional database.sql body). */
    protected function makeArchive(array $manifest, ?string $sqlBody = 'SELECT 1;'): string
    {
        $path = storage_path('app/backups/test-archive.zip');
        @mkdir(dirname($path), 0755, true);

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($sqlBody !== null) {
            $zip->addFromString('database.sql', $sqlBody);
        }

        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->close();

        return $path;
    }

    /** A manifest matching THIS machine — the compatible baseline. */
    protected function compatibleManifest(?string $sqlBody = 'SELECT 1;'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sql');
        file_put_contents($tmp, (string) $sqlBody);
        $manifest = BackupManifest::generate('database', ['database' => $sqlBody !== null ? $tmp : null]);
        @unlink($tmp);

        return $manifest;
    }

    public function test_backup_run_embeds_a_manifest(): void
    {
        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $backup = \App\Models\Backup::latest()->first();
        $this->assertNotNull($backup);
        $this->assertSame('completed', $backup->status);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open(storage_path('app/'.$backup->path)));
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        $this->assertIsArray($manifest);
        $this->assertSame(BackupManifest::FORMAT, $manifest['format']);
        $this->assertSame(PHP_VERSION, $manifest['php']['version']);
        $this->assertSame(config('shopkit.version'), $manifest['app']['shopkit_version']);
        $this->assertSame(config('database.default'), $manifest['database']['driver']);
        $this->assertNotEmpty($manifest['database']['migrations']);
        $this->assertArrayHasKey('products', $manifest['counts']);
        $this->assertArrayHasKey('app_key_fingerprint', $manifest);
    }

    public function test_compatible_archive_passes_all_checks(): void
    {
        $manifest = $this->compatibleManifest();
        $check = BackupCompatibility::check($this->makeArchive($manifest));

        $this->assertSame([], $check->errors);
        // sqlite in tests → the mysql-client check is skipped; ok must be true.
        $this->assertTrue($check->ok);
    }

    public function test_newer_php_backup_is_blocked(): void
    {
        $manifest = $this->compatibleManifest();
        $manifest['php']['version'] = '99.9.0';

        $check = BackupCompatibility::check($this->makeArchive($manifest));

        $this->assertFalse($check->ok);
        $this->assertStringContainsString('PHP too old', implode(' ', $check->errors));
    }

    public function test_newer_shopkit_backup_is_blocked(): void
    {
        $manifest = $this->compatibleManifest();
        $manifest['app']['shopkit_version'] = '99.0.0';

        $check = BackupCompatibility::check($this->makeArchive($manifest));

        $this->assertFalse($check->ok);
        $this->assertStringContainsString('ShopKit 99.0.0', implode(' ', $check->errors));
    }

    public function test_unknown_migrations_block_the_restore(): void
    {
        $manifest = $this->compatibleManifest();
        $manifest['database']['migrations'][] = '2099_01_01_000000_from_the_future';

        $check = BackupCompatibility::check($this->makeArchive($manifest));

        $this->assertFalse($check->ok);
        $this->assertStringContainsString('from_the_future', implode(' ', $check->errors));
    }

    public function test_corrupted_dump_is_detected_by_checksum(): void
    {
        $manifest = $this->compatibleManifest('ORIGINAL CONTENT');
        // Archive carries DIFFERENT sql content than the checksummed original.
        $check = BackupCompatibility::check($this->makeArchive($manifest, 'TAMPERED CONTENT'));

        $this->assertFalse($check->ok);
        $this->assertStringContainsString('checksum mismatch', implode(' ', $check->errors));
    }

    public function test_database_driver_mismatch_is_blocked(): void
    {
        $manifest = $this->compatibleManifest();
        $manifest['database']['driver'] = 'pgsql';

        $check = BackupCompatibility::check($this->makeArchive($manifest));

        $this->assertFalse($check->ok);
        $this->assertStringContainsString('driver mismatch', implode(' ', $check->errors));
    }

    public function test_different_app_key_warns_but_does_not_block(): void
    {
        $manifest = $this->compatibleManifest();
        $manifest['app_key_fingerprint'] = 'deadbeef0000';

        $check = BackupCompatibility::check($this->makeArchive($manifest));

        $this->assertTrue($check->ok);
        $this->assertStringContainsString('APP_KEY differs', implode(' ', $check->warnings));
    }

    public function test_archive_without_manifest_is_flagged_legacy(): void
    {
        $path = storage_path('app/backups/test-archive.zip');
        @mkdir(dirname($path), 0755, true);
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', 'SELECT 1;');
        $zip->close();

        $check = BackupCompatibility::check($path);

        $this->assertFalse($check->ok);
        $this->assertTrue($check->legacy);
        $this->assertStringContainsString('No manifest.json', implode(' ', $check->errors));
    }

    public function test_restore_is_blocked_on_incompatible_archive(): void
    {
        $manifest = $this->compatibleManifest();
        $manifest['php']['version'] = '99.9.0';
        $path = $this->makeArchive($manifest);

        $this->artisan('backup:restore', ['--path' => 'backups/test-archive.zip', '--force' => true, '--no-safety-backup' => true])
            ->expectsOutputToContain('PHP too old')
            ->assertExitCode(1);
    }
}
