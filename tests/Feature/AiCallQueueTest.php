<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\AiImportItem;
use App\Models\QueuedJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiCallQueueTest extends TestCase
{
    use RefreshDatabase;

    /** Insert a fake database-queue row exactly like Laravel's queue writes. */
    protected function queueAiJob(string $jobClass, int $itemId, string $queue = 'default'): int
    {
        $command = 'O:'.strlen("App\\Jobs\\{$jobClass}").':"App\\Jobs\\'.$jobClass.'":1:{s:6:"itemId";i:'.$itemId.';}';
        $payload = json_encode([
            'displayName' => "App\\Jobs\\{$jobClass}",
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'data' => ['commandName' => "App\\Jobs\\{$jobClass}", 'command' => $command],
        ]);

        return (int) DB::table('jobs')->insertGetId([
            'queue' => $queue,
            'payload' => $payload,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);
    }

    protected function batchWithItem(string $status = 'processing', string $name = 'IQOS TEREA Amber'): AiImportItem
    {
        $batch = AiImportBatch::create([
            'name' => 'July batch', 'kind' => 'product', 'provider' => 'anthropic',
            'reviewer_provider' => 'openai', 'status' => $status, 'publish_mode' => 'draft',
            'csv_path' => 'ai/july.csv', 'prompt' => 'p',
        ]);

        return AiImportItem::create([
            'batch_id' => $batch->id, 'row' => ['name' => $name], 'status' => 'pending',
        ]);
    }

    public function test_only_ai_jobs_are_visible_and_source_resolves(): void
    {
        $item = $this->batchWithItem();
        $this->queueAiJob('WriteAiProduct', $item->id);

        // Noise that must NOT appear on the dashboard.
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Mail\\TemplatedMail', 'data' => ['command' => 'x']]),
            'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp,
        ]);

        $this->assertSame(1, QueuedJob::count(), 'Only the AI job is in scope');

        $job = QueuedJob::first();
        $this->assertSame('product', $job->job_kind);
        $this->assertSame($item->id, $job->item_id);
        $this->assertSame('IQOS TEREA Amber', $job->source_name);
        $this->assertTrue($job->will_spend, 'A processing batch means running it spends credit');
    }

    public function test_finished_batch_job_is_marked_as_harmless(): void
    {
        $item = $this->batchWithItem(status: 'completed');
        $this->queueAiJob('WriteAiProduct', $item->id);

        $this->assertFalse(QueuedJob::first()->will_spend);
    }

    public function test_killing_a_job_removes_only_that_queue_row(): void
    {
        $item = $this->batchWithItem();
        $id = $this->queueAiJob('WriteAiProduct', $item->id);

        QueuedJob::findOrFail($id)->delete();

        $this->assertDatabaseMissing('jobs', ['id' => $id]);
        // The source item is untouched — it can be re-run from its batch.
        $this->assertDatabaseHas('ai_import_items', ['id' => $item->id, 'status' => 'pending']);
    }

    public function test_run_command_removes_the_job_row(): void
    {
        $item = $this->batchWithItem(status: 'completed');
        $id = $this->queueAiJob('WriteAiProduct', $item->id);

        // Already-finished batch + we mark item published → command skips the
        // LLM entirely and just cleans up the queue row (no API call, safe test).
        $item->update(['status' => 'published']);

        $this->artisan('ai:run-queued-job', ['job' => $id])->assertSuccessful();

        $this->assertDatabaseMissing('jobs', ['id' => $id]);
    }

    public function test_run_command_on_orphaned_job_is_safe(): void
    {
        $item = $this->batchWithItem();
        $id = $this->queueAiJob('WriteAiProduct', $item->id);
        $item->batch->delete();
        $item->delete();

        $this->artisan('ai:run-queued-job', ['job' => $id])->assertSuccessful();

        $this->assertDatabaseMissing('jobs', ['id' => $id]);
    }

    public function test_list_page_renders_and_shows_the_source(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $item = $this->batchWithItem(name: 'IQOS TEREA Amber Kazakhstan');
        $this->queueAiJob('WriteAiProduct', $item->id);

        \Livewire\Livewire::test(\App\Filament\Resources\AiCallQueueResource\Pages\ListAiCallQueue::class)
            ->assertOk()
            ->assertSee('IQOS TEREA Amber Kazakhstan')
            ->assertSee('July batch');
    }

    public function test_prune_spent_removes_published_and_orphan_jobs_only(): void
    {
        // Published item → spent (no-op), should be pruned.
        $done = $this->batchWithItem();
        $done->update(['status' => 'published']);
        $this->queueAiJob('WriteAiProduct', $done->id);

        // Pending item → genuinely queued, must survive.
        $pending = $this->batchWithItem(name: 'Still Pending');
        $keepId = $this->queueAiJob('WriteAiProduct', $pending->id);

        // Orphan (item deleted) → pruned.
        $orphan = $this->batchWithItem(name: 'Gone');
        $orphanJob = $this->queueAiJob('WriteAiProduct', $orphan->id);
        $orphan->delete();

        $this->assertSame(3, QueuedJob::count());

        QueuedJob::pruneSpent();

        $this->assertSame(1, QueuedJob::count());
        $this->assertDatabaseHas('jobs', ['id' => $keepId]);
        $this->assertDatabaseMissing('jobs', ['id' => $orphanJob]);
    }

    public function test_dashboard_gated_behind_permission(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $seo = User::factory()->create();
        $seo->assignRole('SEO Manager'); // Content+SEO — no AI access
        $this->actingAs($seo);
        $this->assertFalse(\App\Filament\Resources\AiCallQueueResource::canAccess());

        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
        $this->assertTrue(\App\Filament\Resources\AiCallQueueResource::canAccess());
    }
}
