<?php

namespace App\Services\Seo;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * IndexNow: instant URL submission to Bing, Yandex and every other
 * IndexNow-enabled engine (one POST covers them all). Google retired
 * sitemap pings; this is the modern push channel.
 *
 * The key is auto-generated once and served from /{key}.txt (spec
 * requirement — proves domain ownership). Pings fire automatically when
 * products/posts/categories/pages are published or updated, deduplicated
 * per URL for 5 minutes so bulk imports don't spam the endpoint, and are
 * always fire-and-forget: a failed ping can never break a save.
 */
class IndexNow
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** Auto-generate the key on first use; admins can rotate it in settings. */
    public static function key(): string
    {
        $key = (string) setting('seo.indexnow_key', '');

        if ($key === '') {
            $key = strtolower(Str::random(32));
            Setting::set('seo.indexnow_key', $key);
        }

        return $key;
    }

    public static function enabled(): bool
    {
        return (bool) setting('seo.indexnow_enabled', true);
    }

    public static function keyFileUrl(): string
    {
        return url('/'.self::key().'.txt');
    }

    /** Submit one or many URLs. Returns true when accepted (2xx). */
    public function submit(array $urls): bool
    {
        $urls = array_values(array_unique(array_filter($urls)));

        if ($urls === [] || ! self::enabled()) {
            return false;
        }

        // Localhost is never crawlable — don't waste the call in dev.
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (in_array($host, ['localhost', '127.0.0.1'], true) && ! app()->runningUnitTests()) {
            return false;
        }

        try {
            $response = Http::timeout(8)->post(self::ENDPOINT, [
                'host' => $host,
                'key' => self::key(),
                'keyLocation' => self::keyFileUrl(),
                'urlList' => array_slice($urls, 0, 10000),
            ]);

            if (! $response->successful()) {
                Log::channel('single')->info('IndexNow submission rejected: HTTP '.$response->status());
            }

            return $response->successful();
        } catch (\Throwable $e) {
            Log::channel('single')->info('IndexNow submission failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Ping a URL after a content change — deduplicated for 5 minutes so a
     * bulk import editing the same product repeatedly pings once.
     */
    public function ping(string $url): void
    {
        if (! self::enabled() || ! Cache::add('indexnow:'.md5($url), 1, 300)) {
            return;
        }

        // Off the request path: the submit is an 8s-timeout HTTP POST, so a
        // save/delete must not wait on it. Queued (async in prod, inline on
        // the sync queue used in tests). Dedupe already happened above.
        \App\Jobs\PingIndexNow::dispatch($url);
    }
}
