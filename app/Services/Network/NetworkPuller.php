<?php

namespace App\Services\Network;

use App\Models\ConnectedSite;
use App\Models\NetworkRemotePost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Hub-side: pulls a connected site's post list into the local mirror
 * (network_remote_posts) so the admin can browse/filter every site's posts
 * from one table. A full pull (no cursor) also prunes mirror rows the site no
 * longer returns, so deletions on the spoke disappear here too.
 */
class NetworkPuller
{
    public function __construct(protected int $perPage = 100) {}

    /**
     * @return array{0: bool, 1: string, 2: int} [ok, message, count]
     */
    public function pull(ConnectedSite $site): array
    {
        $client = new NetworkClient;
        $seen = [];
        $count = 0;
        $page = 1;

        try {
            do {
                $res = $client->request($site, 'GET', 'posts', [], ['page' => $page, 'per_page' => $this->perPage]);

                foreach ((array) ($res['data'] ?? []) as $row) {
                    if (empty($row['remote_post_id'])) {
                        continue;
                    }

                    NetworkRemotePost::updateOrCreate(
                        ['site_id' => $site->id, 'remote_post_id' => (int) $row['remote_post_id']],
                        [
                            'title' => (string) ($row['title'] ?? 'Untitled'),
                            'slug' => $row['slug'] ?? null,
                            'url' => $row['url'] ?? null,
                            'status' => (string) ($row['status'] ?? 'draft'),
                            'published_at' => $this->date($row['published_at'] ?? null),
                            'remote_updated_at' => $this->date($row['updated_at'] ?? null),
                            'category_name' => $row['category'] ?? null,
                            'author_name' => $row['author'] ?? null,
                            'excerpt' => $row['excerpt'] ?? null,
                            'pulled_at' => now(),
                        ],
                    );

                    $seen[] = (int) $row['remote_post_id'];
                    $count++;
                }

                $lastPage = (int) ($res['last_page'] ?? 1);
                $page++;
            } while ($page <= $lastPage);

            // Full pull → prune anything the site no longer has (deletions).
            NetworkRemotePost::where('site_id', $site->id)
                ->when($seen !== [], fn ($q) => $q->whereNotIn('remote_post_id', $seen))
                ->delete();

            $site->forceFill(['posts_synced_at' => now(), 'status' => 'online', 'last_seen_at' => now(), 'last_error' => null])->save();

            return [true, "Synced {$count} post(s) from {$site->name}.", $count];
        } catch (\Throwable $e) {
            $site->forceFill(['status' => 'error', 'last_error' => Str::limit($e->getMessage(), 480)])->save();

            return [false, $e->getMessage(), $count];
        }
    }

    protected function date(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
