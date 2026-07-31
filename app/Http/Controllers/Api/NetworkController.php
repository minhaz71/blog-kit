<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Network\NetworkPostIngestor;
use App\Support\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                'posts.pull' => false,
                'posts.update' => false,
                'posts.delete' => false,
                'taxonomies.sync' => false,
                'media.sync' => false,
                'authors.sync' => false,
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
}
