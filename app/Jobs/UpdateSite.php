<?php

namespace App\Jobs;

use App\Models\ConnectedSite;
use App\Services\Network\NetworkClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Triggers one connected site's self-update (blogkit:update) via the signed
 * network API. The spoke runs its own backup → pull → migrate in the
 * background; this job just kicks it off and records any trigger error.
 */
class UpdateSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $siteId) {}

    public function handle(): void
    {
        $site = ConnectedSite::find($this->siteId);

        if (! $site || ! $site->is_active) {
            return;
        }

        try {
            (new NetworkClient(timeout: 30))->request($site, 'POST', 'update');
            $site->forceFill(['last_error' => null])->save();
        } catch (\Throwable $e) {
            $site->forceFill([
                'status' => 'error',
                'last_error' => Str::limit('Update trigger failed: '.$e->getMessage(), 480),
            ])->save();
        }
    }
}
