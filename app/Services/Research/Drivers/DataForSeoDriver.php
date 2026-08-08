<?php

namespace App\Services\Research\Drivers;

use App\Services\Research\Contracts\ResearchDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PAID driver: DataForSEO (pay-as-you-go). Real search volume, keyword
 * difficulty, CPC, search intent, and full SERP + People-Also-Ask. Auth is HTTP
 * Basic (login:password). Configured in Admin → SEO → Content strategy →
 * Keyword research. Unavailable (skipped) when credentials are blank.
 */
class DataForSeoDriver implements ResearchDriver
{
    protected const BASE = 'https://api.dataforseo.com/v3/';

    public function name(): string
    {
        return 'dataforseo';
    }

    public function available(): bool
    {
        return $this->login() !== '' && $this->password() !== '';
    }

    public function discover(array $seeds, array $opts = []): array
    {
        if (! $this->available()) {
            return [];
        }

        $seeds = array_values(array_filter(array_map('trim', $seeds)));
        if ($seeds === []) {
            return [];
        }

        [$loc, $lang] = $this->locale($opts);
        $limit = (int) ($opts['target'] ?? 700);

        try {
            $resp = $this->post('dataforseo_labs/google/keyword_ideas/live', [[
                'keywords' => array_slice($seeds, 0, 200),
                'location_code' => $loc,
                'language_code' => $lang,
                'limit' => max(100, min(1000, $limit)),
                'order_by' => ['keyword_info.search_volume,desc'],
            ]]);
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('[research/dataforseo] keyword_ideas failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($this->items($resp) as $it) {
            $kw = trim((string) ($it['keyword'] ?? ''));
            if ($kw === '') {
                continue;
            }
            $info = (array) ($it['keyword_info'] ?? []);
            $out[] = [
                'keyword' => $kw,
                'volume' => isset($info['search_volume']) ? (int) $info['search_volume'] : null,
                'difficulty' => isset($it['keyword_properties']['keyword_difficulty'])
                    ? (int) $it['keyword_properties']['keyword_difficulty'] : null,
                'cpc' => isset($info['cpc']) ? (float) $info['cpc'] : null,
                'intent' => $it['search_intent_info']['main_intent'] ?? null,
                'source' => 'related',
            ];
        }

        return $out;
    }

    public function serp(string $keyword, array $opts = []): array
    {
        if (! $this->available() || trim($keyword) === '') {
            return [];
        }

        [$loc, $lang] = $this->locale($opts);

        try {
            $resp = $this->post('serp/google/organic/live/advanced', [[
                'keyword' => $keyword,
                'location_code' => $loc,
                'language_code' => $lang,
                'depth' => 10,
            ]]);
        } catch (\Throwable $e) {
            Log::channel('ai')->warning('[research/dataforseo] serp failed: '.$e->getMessage());

            return [];
        }

        $urls = [];
        foreach ($this->items($resp) as $item) {
            if (($item['type'] ?? '') === 'organic' && ! empty($item['url'])) {
                $urls[] = $this->host((string) $item['url']);
            }
        }

        return array_values(array_unique(array_filter($urls)));
    }

    /** People-Also-Ask questions for a keyword (from the SERP advanced result). */
    public function peopleAlsoAsk(string $keyword, array $opts = []): array
    {
        if (! $this->available()) {
            return [];
        }

        [$loc, $lang] = $this->locale($opts);

        try {
            $resp = $this->post('serp/google/organic/live/advanced', [[
                'keyword' => $keyword,
                'location_code' => $loc,
                'language_code' => $lang,
                'depth' => 10,
            ]]);
        } catch (\Throwable) {
            return [];
        }

        $questions = [];
        foreach ($this->items($resp) as $item) {
            if (($item['type'] ?? '') === 'people_also_ask') {
                foreach ((array) ($item['items'] ?? []) as $paa) {
                    if (! empty($paa['title'])) {
                        $questions[] = trim((string) $paa['title']);
                    }
                }
            }
        }

        return array_values(array_unique($questions));
    }

    // ── helpers ──────────────────────────────────────────────────────

    protected function post(string $path, array $tasks): array
    {
        return Http::withBasicAuth($this->login(), $this->password())
            ->timeout(120)
            ->post(self::BASE.$path, $tasks)
            ->throw()
            ->json();
    }

    /** DataForSEO nests results at tasks[0].result[0].items (or .result). */
    protected function items(array $resp): array
    {
        $result = $resp['tasks'][0]['result'] ?? [];

        return (array) ($result[0]['items'] ?? $result);
    }

    protected function locale(array $opts): array
    {
        $loc = (int) ($opts['location_code'] ?? setting('research.location_code', 2840) ?: 2840);
        $lang = (string) ($opts['language'] ?? setting('research.language', 'en') ?: 'en');

        return [$loc, $lang];
    }

    protected function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', $host) : $url;
    }

    protected function login(): string
    {
        return trim((string) setting('research.dataforseo_login'));
    }

    protected function password(): string
    {
        return trim((string) setting('research.dataforseo_password'));
    }
}
