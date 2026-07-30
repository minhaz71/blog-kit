<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class CacheWarmCommand extends Command
{
    protected $signature = 'cache:warm {--base= : Base URL to warm (defaults to app.url)}';

    protected $description = 'Warm the public cache by hitting the homepage plus every visible product, category, page, and post URL.';

    public function handle(): int
    {
        $base = rtrim($this->option('base') ?: config('app.url'), '/');
        if (! $base) {
            $this->error('No base URL configured. Set APP_URL or pass --base.');

            return self::FAILURE;
        }

        $urls = collect(['/']);

        Product::query()->where('status', 'published')->select('slug')->chunk(200, fn ($rows) => $urls = $urls->concat($rows->map(fn ($p) => "/product/{$p->slug}")));
        Category::query()->where('is_active', true)->select('slug')->chunk(200, fn ($rows) => $urls = $urls->concat($rows->map(fn ($c) => "/category/{$c->slug}")));
        Page::query()->where('status', 'published')->select('slug')->chunk(200, fn ($rows) => $urls = $urls->concat($rows->map(fn ($p) => "/{$p->slug}")));
        Post::query()->where('status', 'published')->select('slug')->chunk(200, fn ($rows) => $urls = $urls->concat($rows->map(fn ($p) => "/blog/{$p->slug}")));

        $bar = $this->output->createProgressBar($urls->count());
        $ok = 0;
        $fail = 0;

        foreach ($urls as $path) {
            try {
                $resp = Http::timeout(10)->get($base.$path);
                $resp->successful() ? $ok++ : $fail++;
            } catch (Throwable) {
                $fail++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Warmed {$ok} URLs, {$fail} failed.");

        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }
}
