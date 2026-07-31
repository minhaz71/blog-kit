<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A spoke install this hub manages. The primary key IS the network "site ID"
 * referenced by the CSV `site_ids` column and the publisher's target picker.
 * `api_secret` is encrypted at rest; it is the shared HMAC secret used to
 * sign requests to this spoke.
 */
class ConnectedSite extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'api_secret' => 'encrypted',
            'capabilities' => 'array',
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    /** Normalized base URL without a trailing slash. */
    public function baseUrl(): string
    {
        return rtrim((string) $this->base_url, '/');
    }

    /** Full URL for a network API path (path given without leading slash). */
    public function apiUrl(string $path): string
    {
        return $this->baseUrl().'/api/v1/network/'.ltrim($path, '/');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }
}
