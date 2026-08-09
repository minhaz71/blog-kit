<?php

namespace App\Services\Network;

use App\Models\ConnectedSite;
use App\Models\NetworkRemotePost;

/**
 * Builds the internal-link catalog for a CONNECTED SPOKE — the set of that
 * site's own linkable pages, with the spoke's real absolute URLs and funnel
 * identity, so the link planner can wire an article for the spoke to the
 * spoke's content (never the hub's, never localhost).
 *
 * Two tiers, degrading gracefully:
 *   1. Phase-2 spoke that exposes GET /network/link-catalog → the FULL
 *      inventory (posts + blog categories + product categories + products +
 *      home), each with identity. Best coverage.
 *   2. Any older spoke → fall back to the posts the hub already mirrors in
 *      network_remote_posts (identity included since the identity migration)
 *      plus the spoke home page. Still correct, just posts + home only.
 *
 * Entry shape matches {@see \App\Services\Ai\BlogPlanner}:
 *   ['name','url','kind','role','stage','cluster','money']
 */
class NetworkLinkCatalogPuller
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function catalog(ConnectedSite $site, string $scope = 'ecommerce'): array
    {
        // Make sure the mirror is reasonably fresh so links reflect the spoke's
        // current content ("download the links first, then link").
        $this->refreshIfStale($site);

        $full = $this->fetchFullCatalog($site, $scope);
        if ($full !== null) {
            return $full;
        }

        return $this->fromMirror($site);
    }

    /**
     * Ask the spoke for its complete link catalog. Returns null when the spoke
     * doesn't support the endpoint (older version) or the call fails — the
     * caller then falls back to the mirror.
     */
    protected function fetchFullCatalog(ConnectedSite $site, string $scope): ?array
    {
        try {
            $res = (new NetworkClient)->request($site, 'GET', 'link-catalog', [], ['scope' => $scope]);
        } catch (\Throwable) {
            return null;
        }

        if (empty($res['ok']) || ! isset($res['data']) || ! is_array($res['data'])) {
            return null;
        }

        $out = [];
        foreach ($res['data'] as $e) {
            if (empty($e['url']) || empty($e['name'])) {
                continue;
            }
            $out[] = [
                'name' => (string) $e['name'],
                'url' => (string) $e['url'],
                'kind' => (string) ($e['kind'] ?? 'article'),
                'role' => $e['role'] ?? null,
                'stage' => $e['stage'] ?? null,
                'cluster' => $e['cluster'] ?? null,
                'money' => (bool) ($e['money'] ?? in_array($e['kind'] ?? '', ['product', 'product_category'], true)),
            ];
        }

        return $out;
    }

    /**
     * Fallback: build the catalog from posts already mirrored on the hub, plus
     * the spoke's home page. Only published posts are linkable.
     *
     * @return array<int,array<string,mixed>>
     */
    protected function fromMirror(ConnectedSite $site): array
    {
        $catalog = NetworkRemotePost::query()
            ->where('site_id', $site->id)
            ->where('status', 'published')
            ->whereNotNull('url')
            ->orderByDesc('published_at')
            ->limit(80)
            ->get()
            ->map(fn (NetworkRemotePost $p) => [
                'name' => (string) $p->title,
                'url' => (string) $p->url,
                'kind' => 'article',
                'role' => $p->content_role ?: 'spoke',
                'stage' => $p->funnel_stage ?: 'top',
                'cluster' => $p->cluster,
                'money' => false,
            ])
            ->all();

        if ($home = $this->homeUrl($site)) {
            $catalog[] = [
                'name' => $site->name.' (home page)',
                'url' => $home,
                'kind' => 'home', 'role' => null, 'stage' => null, 'cluster' => null, 'money' => false,
            ];
        }

        return $catalog;
    }

    /** Pull the spoke's posts if we haven't recently, so links stay current. */
    protected function refreshIfStale(ConnectedSite $site): void
    {
        // posts_synced_at may be a raw string (not cast on the model) — parse
        // defensively so a fresh mirror is never mistaken for stale, or worse.
        $synced = $site->posts_synced_at;
        if ($synced && ! $synced instanceof \Illuminate\Support\Carbon) {
            try {
                $synced = \Illuminate\Support\Carbon::parse((string) $synced);
            } catch (\Throwable) {
                $synced = null;
            }
        }

        if ($synced && $synced->gt(now()->subMinutes(30))) {
            return;
        }

        try {
            (new NetworkPuller)->pull($site);
        } catch (\Throwable) {
            // Best effort — a stale mirror still gives usable links.
        }
    }

    protected function homeUrl(ConnectedSite $site): ?string
    {
        $base = rtrim((string) $site->base_url, '/');

        return $base !== '' ? $base : null;
    }
}
