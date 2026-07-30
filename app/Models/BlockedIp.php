<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BlockedIp extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['blocked_until' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('security.blocked_ips'));
        static::deleted(fn () => Cache::forget('security.blocked_ips'));
    }

    public function isCurrentlyBlocked(): bool
    {
        return $this->blocked_until === null || $this->blocked_until->isFuture();
    }

    public static function isBlocked(string $ip): bool
    {
        $blocked = Cache::remember('security.blocked_ips', 300, function () {
            return static::all(['ip_address', 'blocked_until'])
                ->filter->isCurrentlyBlocked()
                ->pluck('blocked_until', 'ip_address')
                ->all();
        });

        return array_key_exists($ip, $blocked);
    }

    public static function block(string $ip, string $reason, ?\DateTimeInterface $until = null): ?self
    {
        // Never AUTO-ban loopback: local dev browsing, automated test runs and
        // health checks all come from 127.0.0.1/::1, and banning them 403s the
        // whole site (the store owner's own machine) with nothing in the normal
        // error log. Explicit admin blocks still work — they create rows directly.
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return null;
        }

        return static::updateOrCreate(
            ['ip_address' => $ip],
            ['reason' => $reason, 'blocked_until' => $until],
        );
    }
}
