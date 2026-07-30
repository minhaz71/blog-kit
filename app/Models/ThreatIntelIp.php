<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A single threat-actor IP from a real-time blocklist feed. The full set is
 * cached as a lookup map so the firewall checks it with zero queries.
 */
class ThreatIntelIp extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public const CACHE_KEY = 'security.threat_ips';

    /** Is this IP on the threat feed? O(1) against a cached set. */
    public static function contains(string $ip): bool
    {
        return isset(self::lookup()[$ip]);
    }

    /** @return array<string,bool> ip => true */
    public static function lookup(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, fn () => self::pluck('ip_address')->flip()->map(fn () => true)->all());
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
