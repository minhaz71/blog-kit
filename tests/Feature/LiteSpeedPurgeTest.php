<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Services\Performance\LiteSpeedPurger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The cache purge must NOT make a blocking outbound HTTP call on every
 * save/delete (that fetched the full homepage with a 3s timeout and made
 * deletes crawl). It rides out on the response header instead.
 */
class LiteSpeedPurgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_queues_a_flag_without_a_blocking_homepage_fetch(): void
    {
        Http::fake();

        app(LiteSpeedPurger::class)->purgeProduct('widget');

        // The old code fetched the full homepage here (3s timeout) on every
        // product save/delete — now it only queues a flag, no outbound call.
        Http::assertNothingSent();
        $this->assertNotNull(cache()->get('litespeed.purge'));
    }

    public function test_a_batch_coalesces_many_purges_into_one_purge_all(): void
    {
        Http::fake();
        cache()->forget('litespeed.purge');
        $purger = app(LiteSpeedPurger::class);

        LiteSpeedPurger::beginBatch();
        $purger->purgeProduct('widget-a');
        $purger->purgeProduct('widget-b');
        $purger->purgeProduct('widget-c');

        // Held during the batch — the flag is not rewritten per record.
        $this->assertNull(cache()->get('litespeed.purge'));

        LiteSpeedPurger::endBatch();

        // One purge-all covers the whole batch (no per-record clobbering).
        $this->assertSame('*', cache()->get('litespeed.purge'));
    }

    public function test_middleware_emits_queued_purge_as_a_response_header(): void
    {
        Setting::set('performance.litespeed_enabled', true);

        app(LiteSpeedPurger::class)->purgeTags(['products.widget', 'home']);

        $response = $this->get('/');

        $response->assertHeader('X-LiteSpeed-Purge');
        // Consumed once — a follow-up request no longer carries it.
        $this->assertNull(cache()->get('litespeed.purge'));
    }
}
