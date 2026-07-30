<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Services\Seo\IndexNow;
use Illuminate\Console\Command;

/**
 * Bulk IndexNow submission: pushes every published URL to Bing/Yandex (and
 * every IndexNow engine) in one batch call. The spec allows 10,000 URLs per
 * POST — far above this store's size. Run once at launch, after a domain
 * change, or after a big import; day-to-day changes ping automatically.
 */
class SeoIndexNowSubmit extends Command
{
    protected $signature = 'seo:indexnow-submit {--dry-run : List the URLs without submitting}';

    protected $description = 'Submit every published URL (home, products, categories, posts, pages) to IndexNow in one batch.';

    public function handle(IndexNow $indexNow): int
    {
        $urls = collect([url('/'), route('shop'), route('blog.index')])
            ->merge(Product::query()->where('status', 'published')->get()->map->url())
            ->merge(Category::query()->where('is_active', true)->get()->map->url())
            ->merge(Post::query()->where('status', 'published')->get()->map->url())
            ->merge(PostCategory::query()->get()->map(fn ($c) => route('blog.category', $c->slug)))
            ->merge(Page::query()->where('status', 'published')->get()->map->url())
            ->unique()
            ->values();

        $this->line($urls->count().' published URL(s) collected.');

        if ($this->option('dry-run')) {
            $urls->each(fn ($u) => $this->line("  {$u}"));
            $this->info('Dry run — nothing submitted.');

            return self::SUCCESS;
        }

        if (! IndexNow::enabled()) {
            $this->warn('IndexNow is disabled in SEO settings — nothing submitted.');

            return self::SUCCESS;
        }

        $accepted = $indexNow->submit($urls->all());

        $accepted
            ? $this->info('Batch accepted by IndexNow — Bing/Yandex will recrawl these URLs shortly.')
            : $this->warn('IndexNow did not accept the batch (dev host, disabled, or endpoint error — see laravel.log).');

        return self::SUCCESS;
    }
}
