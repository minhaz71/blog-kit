<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Version;
use Illuminate\Http\JsonResponse;

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
                'posts.push' => false,
                'posts.pull' => false,
                'posts.update' => false,
                'posts.delete' => false,
                'taxonomies.sync' => false,
                'media.sync' => false,
                'authors.sync' => false,
            ],
        ]);
    }
}
