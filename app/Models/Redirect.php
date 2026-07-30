<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_regex' => 'boolean',
            'is_active' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('redirects.active'));
        static::deleted(fn () => Cache::forget('redirects.active'));
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function recordHit(): void
    {
        $this->increment('hits');
        $this->forceFill(['last_hit_at' => now()])->saveQuietly();
    }

    /** Normalize a path for matching: leading slash, no trailing slash (except root). */
    public static function normalizePath(string $path): string
    {
        $path = '/'.ltrim(trim($path), '/');

        return $path !== '/' ? rtrim($path, '/') : '/';
    }
}
