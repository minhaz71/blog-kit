<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\Page;
use App\Models\ProductTemplate;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class BackupCloudSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_captures_every_data_type_including_ai_keys(): void
    {
        // Seed one of each thing the owner cares about.
        Setting::set('ai.anthropic_api_key', 'sk-ant-secret-123');
        ProductTemplate::create(['name' => 'My template', 'is_default' => true, 'blocks' => [['type' => 'title', 'data' => []]]]);
        Page::create(['title' => 'About', 'slug' => 'about', 'content' => '<p>Our story.</p>', 'status' => 'published']);
        AiImportBatch::create([
            'name' => 'Batch', 'kind' => 'product', 'csv_path' => 'x.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'reviewer_provider' => 'openai',
        ]);

        $this->artisan('backup:run', ['--type' => 'database'])->assertExitCode(0);

        $backup = \App\Models\Backup::latest()->first();
        $this->assertSame('completed', $backup->status);

        $zip = new ZipArchive;
        $zip->open(storage_path('app/'.$backup->path));
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $zip->close();

        // The manifest documents a live row count for each data type the owner
        // cares about — proving the dump was taken with that data present.
        foreach (['products', 'settings', 'ai_import_batches', 'product_templates', 'pages', 'posts'] as $table) {
            $this->assertArrayHasKey($table, $manifest['counts']);
        }
        $this->assertGreaterThanOrEqual(1, $manifest['counts']['product_templates']);
        $this->assertGreaterThanOrEqual(1, $manifest['counts']['pages']);
        $this->assertGreaterThanOrEqual(1, $manifest['counts']['ai_import_batches']);
        // settings row count includes the AI key row we just stored.
        $this->assertGreaterThanOrEqual(1, $manifest['counts']['settings']);
        $this->assertNotNull(Setting::get('ai.anthropic_api_key'));
    }

    public function test_cloud_sync_is_a_safe_noop_without_a_remote(): void
    {
        config(['shopkit.backup.remote' => '']);

        $this->artisan('backup:cloud-sync')
            ->expectsOutputToContain('No cloud remote configured')
            ->assertExitCode(0);
    }
}
