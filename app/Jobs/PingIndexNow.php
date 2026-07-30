<?php

namespace App\Jobs;

use App\Services\Seo\IndexNow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Submits one URL to IndexNow (Bing/Yandex) off the request path. The
 * submit is a synchronous HTTP POST with an 8s timeout — queuing it keeps
 * saving/deleting a product, post, page or category instant instead of
 * stalling the admin action on an outbound round-trip. On a sync queue
 * (tests) it still runs inline, so behaviour is unchanged there.
 */
class PingIndexNow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $url) {}

    public function handle(IndexNow $indexNow): void
    {
        $indexNow->submit([$this->url]);
    }
}
