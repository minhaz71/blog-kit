<?php

namespace App\Services\Performance;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Wrapper around Cache::remember() that pre-loads Collection/Model classes
 * before unserialize() so cached Eloquent Collections don't rehydrate to
 * __PHP_Incomplete_Class. Falls back to running the callback if the cached
 * value is broken.
 */
class SafeCache
{
    public static function remember(string $key, int $ttl, Closure $callback): mixed
    {
        // Force Eloquent Collection + base Support Collection classes to be autoloaded
        // BEFORE any unserialize() call inside Cache::get()/remember().
        class_exists(EloquentCollection::class);
        class_exists(SupportCollection::class);

        try {
            $value = Cache::remember($key, $ttl, $callback);
        } catch (Throwable) {
            Cache::forget($key);

            return $callback();
        }

        // If deserialization returned a broken object, blow away the cache entry
        // and recompute freshly.
        if (self::isBroken($value)) {
            Cache::forget($key);

            return $callback();
        }

        return $value;
    }

    /** Detect __PHP_Incomplete_Class or a Collection containing incomplete objects. */
    protected static function isBroken(mixed $value): bool
    {
        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }
        if ($value instanceof SupportCollection || is_array($value)) {
            foreach ($value as $item) {
                if ($item instanceof \__PHP_Incomplete_Class) {
                    return true;
                }
            }
        }

        return false;
    }
}
