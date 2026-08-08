<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The AI blog batch live monitor must be reachable on a blog-only site (store
 * off), even though it shares the ecommerce product-batch resource/route.
 */
class BlogBatchMonitorAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed();
        // Replicate a blog-only site: store OFF.
        \App\Models\Setting::set('modules.ecommerce_enabled', '0');
        \Illuminate\Support\Facades\Cache::forget('settings.modules');

        $u = User::factory()->create(['is_active' => true]);
        $u->assignRole('Super Admin');

        return $u;
    }

    protected function batch(string $kind): AiImportBatch
    {
        return AiImportBatch::create(['kind' => $kind, 'name' => ucfirst($kind), 'status' => 'processing', 'csv_path' => '', 'prompt' => '']);
    }

    public function test_blog_batch_monitor_opens_with_store_off(): void
    {
        $this->actingAs($this->admin());
        $this->assertFalse(ecommerce_enabled(), 'precondition: store is off');

        $this->get("/admin/ai-import-batches/{$this->batch('blog')->id}/monitor")
            ->assertSuccessful();
    }

    public function test_monitor_forbidden_without_ai_batch_access(): void
    {
        $this->seed();
        \App\Models\Setting::set('modules.ecommerce_enabled', '0');
        \Illuminate\Support\Facades\Cache::forget('settings.modules');

        // A user with no AI-batch access at all (store off) cannot open it.
        $stranger = User::factory()->create(['is_active' => true]);
        $this->actingAs($stranger);

        $this->get("/admin/ai-import-batches/{$this->batch('blog')->id}/monitor")->assertForbidden();
    }
}
