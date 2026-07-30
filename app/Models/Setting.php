<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    protected static function booted(): void
    {
        static::saved(fn (Setting $s) => Cache::forget("settings.{$s->group}"));
        static::deleted(fn (Setting $s) => Cache::forget("settings.{$s->group}"));
    }

    /** Get a setting via "group.key" dot notation, cached per group. */
    public static function get(string $dotKey, mixed $default = null): mixed
    {
        [$group, $key] = array_pad(explode('.', $dotKey, 2), 2, null);

        if ($key === null) {
            return $default;
        }

        $groupValues = static::group($group);

        return $groupValues[$key] ?? $default;
    }

    public static function set(string $dotKey, mixed $value): void
    {
        [$group, $key] = explode('.', $dotKey, 2);

        try {
            static::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
        } catch (Throwable) {
            // Silently ignore — settings table may not exist yet during install.
        }
    }

    public static function group(string $group): array
    {
        return Cache::rememberForever("settings.{$group}", function () use ($group) {
            try {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return static::where('group', $group)->pluck('value', 'key')->all();
            } catch (Throwable) {
                return [];
            }
        });
    }
}
