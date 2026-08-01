<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\Network\NetworkPostIngestor;
use App\Services\Network\NetworkPostPayload;
use App\Support\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Spoke-side network API (this install answering a hub). All routes here run
 * behind the `network.signed` middleware, so the request is already
 * authenticated by HMAC. Phase 1 = handshake only (ping + capabilities);
 * content ingest/sync endpoints arrive in later phases.
 */
class NetworkController extends Controller
{
    public function ping(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'name' => (string) setting('general.site_name', config('app.name')),
            'url' => rtrim((string) config('app.url'), '/'),
            'version' => Version::core(),
            'role' => network_role(),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function capabilities(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'name' => (string) setting('general.site_name', config('app.name')),
            'url' => rtrim((string) config('app.url'), '/'),
            'version' => Version::core(),
            'role' => network_role(),
            'protocol' => 'blogkit-network/1',
            // Declares which sync surfaces this spoke supports. Phase 1 ships
            // the handshake; later phases flip these on as endpoints land.
            'capabilities' => [
                'handshake' => true,
                'posts.push' => true,
                'posts.pull' => true,
                'posts.update' => true,
                'posts.delete' => true,
                'taxonomies.sync' => true,
                'media.sync' => true,
                'authors.sync' => true,
                'remote.update' => (bool) setting('network.allow_remote_update', true),
            ],
        ]);
    }

    /**
     * Ingest a post pushed from a hub. Idempotent on the origin identity, so
     * re-pushing the same hub post updates the existing local copy. The hub is
     * identified by the verified signature key (added to the request by the
     * network.signed middleware is not needed — we read the key header, which
     * the middleware already proved belongs to this site's own credentials;
     * for a spoke, that key IS its own key, so we namespace origins by it).
     */
    public function storePost(Request $request): JsonResponse
    {
        $data = (array) $request->json()->all();

        if (blank($data['network_post_id'] ?? null) || blank($data['title'] ?? null)) {
            return response()->json(['ok' => false, 'error' => 'network_post_id and title are required.'], 422);
        }

        // The origin is namespaced by the calling hub's key so two different
        // hubs can never collide on the same local post.
        $hubKey = (string) $request->header(\App\Services\Network\NetworkSignature::HEADER_KEY, 'hub');

        $post = (new NetworkPostIngestor)->apply($hubKey, $data);

        return response()->json([
            'ok' => true,
            'remote_post_id' => $post->id,
            'slug' => $post->slug,
            'url' => $post->url(),
            'status' => $post->status,
        ]);
    }

    /**
     * Paginated list of this site's posts for a hub to mirror and browse.
     * `?since=<iso8601>` returns only posts updated after that time
     * (incremental pulls); `?page` + `?per_page` paginate.
     */
    public function listPosts(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        $query = Post::query()->with(['category', 'author'])->orderByDesc('updated_at');

        if ($since = $request->query('since')) {
            try {
                $query->where('updated_at', '>', Carbon::parse((string) $since));
            } catch (\Throwable) {
                // ignore an unparseable cursor — fall back to a full page
            }
        }

        $page = $query->paginate($perPage);

        return response()->json([
            'ok' => true,
            'data' => collect($page->items())->map(fn (Post $p) => [
                'remote_post_id' => $p->id,
                'title' => (string) $p->title,
                'slug' => (string) $p->slug,
                'url' => $p->url(),
                'status' => (string) $p->status,
                'published_at' => $p->published_at?->toIso8601String(),
                'updated_at' => $p->updated_at?->toIso8601String(),
                'category' => $p->category?->name,
                'author' => $p->author?->name,
                'excerpt' => Str::limit(strip_tags((string) $p->excerpt), 200),
                // Lets a hub detect a spoke-side edit (divergence from what it
                // last pushed) — computed the same way as the hub's push hash.
                'content_hash' => NetworkPostPayload::hash(NetworkPostPayload::for($p)),
            ])->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    /**
     * Delete a network-managed post pushed by the calling hub. Scoped to this
     * hub's own origin namespace so one hub can never delete another hub's (or
     * a locally authored) post. Idempotent: already-gone returns ok.
     */
    public function deletePost(Request $request, int $id): JsonResponse
    {
        $hubKey = (string) $request->header(\App\Services\Network\NetworkSignature::HEADER_KEY, 'hub');
        $origin = $hubKey.':'.$id;

        $post = Post::where('network_origin', $origin)->first();

        if (! $post) {
            return response()->json(['ok' => true, 'deleted' => false, 'reason' => 'not found (already deleted or not managed by this hub)']);
        }

        $post->delete(); // soft delete (Post uses SoftDeletes)

        return response()->json(['ok' => true, 'deleted' => true]);
    }

    /**
     * Trigger this site's own software update (blogkit:update) in the
     * background — the same flow as Admin → Security → System updates
     * (preflight + mandatory backup + git pull + migrate). Opt-in per site via
     * the "Allow remote updates" setting so a hub can't force an update on a
     * site that hasn't allowed it.
     */
    public function update(Request $request): JsonResponse
    {
        if (! (bool) setting('network.allow_remote_update', true)) {
            return response()->json(['ok' => false, 'error' => 'Remote updates are disabled on this site.'], 403);
        }

        if (! \App\Support\Version::isGitRepo()) {
            return response()->json(['ok' => false, 'error' => 'This site is not a git checkout — cannot self-update.'], 409);
        }

        $started = \App\Support\BackgroundProcess::artisan(['blogkit:update']);

        return response()->json([
            'ok' => $started,
            'started' => $started,
            'message' => $started
                ? 'Update started (backup → pull → migrate) in the background.'
                : 'Could not spawn the update process on this host; run `php artisan blogkit:update` over SSH.',
        ], $started ? 202 : 500);
    }
}
