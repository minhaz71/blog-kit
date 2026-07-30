<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\Performance\SafeCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SafeCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_remember_returns_the_callback_value_first_time(): void
    {
        Cache::forget('t');
        $out = SafeCache::remember('t', 60, fn () => ['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $out);
    }

    public function test_remember_returns_cached_value_on_second_call(): void
    {
        Cache::forget('counter');
        $hits = 0;
        SafeCache::remember('counter', 60, function () use (&$hits) { $hits++; return 'ok'; });
        SafeCache::remember('counter', 60, function () use (&$hits) { $hits++; return 'ok'; });
        $this->assertSame(1, $hits, 'callback should have been invoked only once.');
    }

    public function test_remember_recovers_when_cached_value_is_broken(): void
    {
        // Simulate a corrupted __PHP_Incomplete_Class in cache (as happens with
        // some Eloquent Collection rehydration paths).
        Cache::put('broken', new \__PHP_Incomplete_Class, 60);

        $out = SafeCache::remember('broken', 60, fn () => ['ok' => true]);
        $this->assertSame(['ok' => true], $out);
    }

    public function test_remember_works_with_eloquent_collection(): void
    {
        Category::create(['name' => 'X', 'slug' => 'x', 'is_active' => true]);
        Cache::forget('cats');

        $first = SafeCache::remember('cats', 60, fn () => Category::all());
        $second = SafeCache::remember('cats', 60, fn () => Category::all());

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first->first()->slug, $second->first()->slug ?? null);
    }
}
