<?php

namespace App\Services\Seo;

use App\Models\PageSpeedSnapshot;
use Illuminate\Support\Facades\Http;

/**
 * Google PageSpeed Insights snapshots. Works without an API key at low
 * volume; add one in SEO settings for reliable daily quota. Fetching is
 * cron-only (never during a page view) and capped per run, so it can
 * never slow the site or exhaust quota.
 */
class PageSpeedService
{
    public function snapshot(string $url, string $strategy = 'mobile'): ?PageSpeedSnapshot
    {
        $params = [
            'url' => $url,
            'strategy' => $strategy,
            'category' => 'performance',
        ];

        if ($key = (string) setting('seo.pagespeed_api_key')) {
            $params['key'] = $key;
        }

        $response = Http::timeout(60)
            ->get('https://www.googleapis.com/pagespeedonline/v5/runPagespeed', $params);

        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $audits = $data['lighthouseResult']['audits'] ?? [];

        return PageSpeedSnapshot::create([
            'url' => $url,
            'strategy' => $strategy,
            'performance' => (int) round(100 * (float) ($data['lighthouseResult']['categories']['performance']['score'] ?? 0)),
            'lcp' => round(((float) ($audits['largest-contentful-paint']['numericValue'] ?? 0)) / 1000, 2),
            'cls' => round((float) ($audits['cumulative-layout-shift']['numericValue'] ?? 0), 3),
            // INP field data when Google has it for this URL; null otherwise.
            'inp' => ($inp = $data['loadingExperience']['metrics']['INTERACTION_TO_NEXT_PAINT']['percentile'] ?? null) !== null ? (int) $inp : null,
            'fetched_at' => now(),
        ]);
    }

    /**
     * The URLs worth tracking, most important first: home, categories,
     * then products with the most inbound internal links.
     *
     * @return array<int, string>
     */
    public function keyUrls(int $productLimit = 10): array
    {
        $urls = [url('/')];

        foreach (\App\Models\Category::query()->where('is_active', true)->orderBy('sort_order')->limit(10)->get() as $category) {
            $urls[] = $category->url();
        }

        $topProducts = \App\Models\InternalLink::query()
            ->selectRaw('target_id, COUNT(*) as links')
            ->where('target_type', \App\Models\Product::class)
            ->groupBy('target_id')
            ->orderByDesc('links')
            ->limit($productLimit)
            ->pluck('target_id');

        $products = \App\Models\Product::query()
            ->where('status', 'published')
            ->when($topProducts->isNotEmpty(), fn ($q) => $q->whereIn('id', $topProducts))
            ->limit($productLimit)
            ->get();

        foreach ($products as $product) {
            $urls[] = $product->url();
        }

        return array_values(array_unique($urls));
    }
}
