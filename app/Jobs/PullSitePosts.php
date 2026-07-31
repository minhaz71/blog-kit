<?php

namespace App\Jobs;

use App\Models\ConnectedSite;
use App\Services\Network\NetworkPuller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pulls one connected site's posts into the hub mirror. Queued so a slow or
 * offline site never blocks the request cycle; errors are recorded on the
 * site row by NetworkPuller.
 */
class PullSitePosts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [15, 45];

    public function __construct(public int $siteId) {}

    public function handle(): void
    {
        $site = ConnectedSite::find($this->siteId);

        if (! $site || ! $site->is_active) {
            return;
        }

        (new NetworkPuller)->pull($site);
    }
}
