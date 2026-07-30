<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Purges the LiteSpeed cache by tag or entirely. Works two ways:
 *  - response header on the next request (X-LiteSpeed-Purge) via a queued flag
 *  - direct HTTP request to the site with the purge header (for CLI/cron)
 */
class LiteSpeedPurger
{
    /** While > 0, per-item purges are coalesced into ONE purge-all at endBatch(). */
    protected static int $deferDepth = 0;

    /** Set when a purge was requested during a deferred batch. */
    protected static bool $deferredDirty = false;

    /**
     * Begin a bulk operation: individual purges are held so a batch delete
     * of 200 products doesn't rewrite the purge flag 200 times (each write
     * clobbering the last one's tags, so only the final record's cache
     * actually cleared). Pair with endBatch(), which purges everything once.
     */
    public static function beginBatch(): void
    {
        self::$deferDepth++;
    }

    /** End a bulk operation: if anything asked to purge, purge all — once. */
    public static function endBatch(): void
    {
        if (self::$deferDepth > 0) {
            self::$deferDepth--;
        }

        if (self::$deferDepth === 0 && self::$deferredDirty) {
            self::$deferredDirty = false;
            app(self::class)->purgeAll();
        }
    }

    /** Queue a purge-all; header is emitted by the LiteSpeedCache middleware on the next response. */
    public function purgeAll(): void
    {
        if (self::$deferDepth > 0) {
            self::$deferredDirty = true;

            return;
        }

        cache()->put('litespeed.purge', '*', 300);
        $this->ping('*');
    }

    /** @param string[] $tags */
    public function purgeTags(array $tags): void
    {
        if (self::$deferDepth > 0) {
            self::$deferredDirty = true;

            return;
        }

        $value = collect($tags)->map(fn ($t) => "tag={$t}")->implode(', ');
        cache()->put('litespeed.purge', $value, 300);
        $this->ping($value);
    }

    public function purgeProduct(string $slug): void
    {
        $this->purgeTags(['products.'.$slug, 'home', 'categories']);
    }

    public function purgeCategory(string $slug): void
    {
        $this->purgeTags(['categories.'.$slug, 'home']);
    }

    /** Consume the queued purge value (used by middleware). */
    public function pendingPurge(): ?string
    {
        return cache()->pull('litespeed.purge');
    }

    protected function ping(string $purgeValue): void
    {
        // In a web request the purge rides out on the response header (see
        // LiteSpeedCache middleware) — making a blocking HTTP call here as
        // well would fetch the full homepage on every save/delete and stall
        // the admin action for up to 3s. So the direct ping is ONLY for
        // console contexts (cron/CLI mutations) that have no response to
        // carry the header, and never during tests.
        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            return;
        }

        try {
            Http::timeout(3)->withHeaders(['X-LiteSpeed-Purge-Request' => $purgeValue])->get(config('app.url'));
        } catch (\Throwable $e) {
            Log::debug('LiteSpeed purge ping skipped: '.$e->getMessage());
        }
    }
}
