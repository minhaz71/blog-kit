<?php

namespace App\Jobs;

use App\Models\ConnectedSite;
use App\Models\NetworkPostLink;
use App\Models\NetworkRemotePost;
use App\Services\Network\NetworkClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Removes a hub post's copy from one connected site, then drops the local
 * link + mirror row. $networkPostId is the hub's own post id (the origin key
 * on the spoke). If the site is gone/inactive, the link is cleaned up locally.
 */
class DeletePostFromSite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $networkPostId, public int $siteId, public int $linkId) {}

    public function handle(): void
    {
        $link = NetworkPostLink::find($this->linkId);
        $site = ConnectedSite::find($this->siteId);

        if (! $link) {
            return;
        }

        if (! $site) {
            $link->delete();

            return;
        }

        try {
            (new NetworkClient)->request($site, 'DELETE', 'posts/'.$this->networkPostId);

            NetworkRemotePost::where('site_id', $site->id)
                ->where('remote_post_id', $link->remote_post_id)
                ->delete();

            $link->delete();
        } catch (\Throwable $e) {
            $link->update(['status' => 'failed', 'last_error' => Str::limit('Delete failed: '.$e->getMessage(), 480)]);

            throw $e;
        }
    }
}
