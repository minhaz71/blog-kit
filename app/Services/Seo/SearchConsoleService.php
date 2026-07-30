<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;

/**
 * Google Search Console + GA4 data, service-account authenticated.
 *
 *  - searchAnalytics(): clicks/impressions/CTR/position per page.
 *  - inspectUrl(): real index status per URL (quota: 2,000/day — the sync
 *    command only inspects the top pages).
 *  - organicSessions(): GA4 organic-search sessions per landing page.
 *
 * All methods assume credentials exist — callers check configured() first.
 */
class SearchConsoleService
{
    public function __construct(protected GoogleServiceAccount $auth) {}

    public static function configured(): bool
    {
        return GoogleServiceAccount::configured() && trim((string) setting('seo.gsc_property', '')) !== '';
    }

    protected function property(): string
    {
        return trim((string) setting('seo.gsc_property'));
    }

    /**
     * Page-level search performance for the last N days.
     *
     * @return array<int, array{url: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function searchAnalytics(int $days = 28, int $limit = 500): array
    {
        $token = $this->auth->accessToken(['https://www.googleapis.com/auth/webmasters.readonly']);

        $rows = Http::withToken($token)
            ->timeout(30)
            ->post('https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode($this->property()).'/searchAnalytics/query', [
                'startDate' => now()->subDays($days)->toDateString(),
                'endDate' => now()->toDateString(),
                'dimensions' => ['page'],
                'rowLimit' => $limit,
            ])
            ->throw()
            ->json('rows', []);

        return collect($rows)->map(fn (array $row) => [
            'url' => (string) $row['keys'][0],
            'clicks' => (int) $row['clicks'],
            'impressions' => (int) $row['impressions'],
            'ctr' => round((float) $row['ctr'] * 100, 2),
            'position' => round((float) $row['position'], 1),
        ])->all();
    }

    /**
     * URL Inspection: is this page actually indexed?
     *
     * @return array{verdict: string, coverage: ?string, last_crawl: ?string}
     */
    public function inspectUrl(string $url): array
    {
        $token = $this->auth->accessToken(['https://www.googleapis.com/auth/webmasters.readonly']);

        $result = Http::withToken($token)
            ->timeout(30)
            ->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
                'inspectionUrl' => $url,
                'siteUrl' => $this->property(),
            ])
            ->throw()
            ->json('inspectionResult.indexStatusResult', []);

        return [
            'verdict' => (string) ($result['verdict'] ?? 'VERDICT_UNSPECIFIED'),
            'coverage' => $result['coverageState'] ?? null,
            'last_crawl' => $result['lastCrawlTime'] ?? null,
        ];
    }

    /**
     * GA4: organic-search sessions per landing page for the last N days.
     * Returns [] when no GA4 property is configured.
     *
     * @return array<string, int> path => sessions
     */
    public function organicSessions(int $days = 28): array
    {
        $propertyId = trim((string) setting('seo.ga4_property_id', ''));

        if ($propertyId === '') {
            return [];
        }

        $token = $this->auth->accessToken(['https://www.googleapis.com/auth/analytics.readonly']);

        $rows = Http::withToken($token)
            ->timeout(30)
            ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport", [
                'dateRanges' => [['startDate' => "{$days}daysAgo", 'endDate' => 'today']],
                'dimensions' => [['name' => 'landingPage']],
                'metrics' => [['name' => 'sessions']],
                'dimensionFilter' => [
                    'filter' => [
                        'fieldName' => 'sessionDefaultChannelGroup',
                        'stringFilter' => ['value' => 'Organic Search'],
                    ],
                ],
                'limit' => 1000,
            ])
            ->throw()
            ->json('rows', []);

        return collect($rows)->mapWithKeys(fn (array $row) => [
            (string) $row['dimensionValues'][0]['value'] => (int) $row['metricValues'][0]['value'],
        ])->all();
    }
}
