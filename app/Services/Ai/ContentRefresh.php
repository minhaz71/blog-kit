<?php

namespace App\Services\Ai;

use App\Models\AiImportBatch;
use App\Models\Post;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Builds a "refresh" batch that rewrites EXISTING content in place. Each
 * item points at a live product/post (product_id/post_id + reserved_slug
 * preset) and carries a snapshot of the current copy under the `_current`
 * key, so the writer can analyze it, preserve its facts, fill competitor
 * gaps and rewrite it for EEAT + Google/Bing/AI. The idempotent publishers
 * update in place; price, SKU, stock, relations and the slug/URL are kept.
 *
 * Reuses the whole proven engine (runner, review loop, deterministic gate,
 * finalize) — refresh is just a batch with `refresh = true` and pre-built
 * items, so the runner skips CSV parsing and writes straight away.
 */
class ContentRefresh
{
    /**
     * @param  Collection<int,Product>  $products
     * @param  array{provider?:string,model?:string,publish_mode?:string,brief?:string}  $opts
     */
    public function products(Collection $products, array $opts = []): AiImportBatch
    {
        $batch = $this->makeBatch('product', $products->count(), $opts);

        foreach ($products as $product) {
            // Load seoMeta with a fresh query, not the instance's relation
            // cache — a saved-observer may have cached it as null earlier.
            $seo = $product->seoMeta()->first();

            $batch->items()->create([
                'product_id' => $product->id,
                'reserved_slug' => $product->slug,
                'status' => 'pending',
                'row' => [
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'regular_price' => $product->price,
                    'sale_price' => $product->sale_price,
                    'specifications' => $product->specifications,
                    'keywords' => $this->keywordsFor($seo),
                    '_current' => [
                        'meta_title' => $seo?->title,
                        'meta_description' => $seo?->description,
                        'short_description' => $product->short_description,
                        'description' => $product->description,
                    ],
                ],
            ]);
        }

        return tap($batch)->save();
    }

    /**
     * @param  Collection<int,Post>  $posts
     * @param  array{provider?:string,model?:string,publish_mode?:string,brief?:string}  $opts
     */
    public function posts(Collection $posts, array $opts = []): AiImportBatch
    {
        $batch = $this->makeBatch('blog', $posts->count(), $opts);
        $batch->update(['link_scope' => 'ecommerce', 'link_catalog' => (new BlogPlanner)->buildLinkCatalog('ecommerce')]);

        foreach ($posts as $post) {
            $seo = $post->seoMeta()->first();
            $batch->items()->create([
                'post_id' => $post->id,
                'status' => 'pending',
                'row' => [
                    'name' => $post->title,
                    'keywords' => $this->keywordsFor($seo),
                    '_current' => [
                        'meta_title' => $seo?->title,
                        'meta_description' => $seo?->description,
                        'description' => $post->content,
                    ],
                ],
            ]);
        }

        return $batch;
    }

    private function makeBatch(string $kind, int $total, array $opts): AiImportBatch
    {
        $provider = $opts['provider'] ?? 'anthropic';

        $batch = AiImportBatch::create([
            'kind' => $kind,
            'refresh' => true,
            'csv_path' => '',
            'name' => 'Refresh — '.ucfirst($kind).' '.now()->format('M j, H:i'),
            'user_id' => auth()->id(),
            'prompt' => $opts['brief'] ?? AiImportBatch::DEFAULT_STORE_BRIEF,
            'provider' => $provider,
            'model' => $opts['model'] ?? null,
            'reviewer_provider' => $opts['reviewer_provider'] ?? $provider,
            'reviewer_model' => $opts['reviewer_model'] ?? null,
            'review_passes' => (int) ($opts['review_passes'] ?? 3),
            'publish_mode' => in_array($opts['publish_mode'] ?? '', ['draft', 'publish'], true) ? $opts['publish_mode'] : 'draft',
            'require_approval' => (bool) ($opts['require_approval'] ?? true),
            'link_scope' => 'ecommerce',
            'status' => 'processing',
            'total_items' => $total,
        ]);

        if ($kind === 'product' && empty($batch->link_catalog)) {
            $batch->link_catalog = (new BlogPlanner)->buildLinkCatalog('ecommerce');
        }

        return $batch;
    }

    /** Start the shared background runner; fall back to queued per-item jobs. */
    public function start(AiImportBatch $batch): void
    {
        $launched = \App\Support\BackgroundProcess::artisan(['ai:run-batch', (string) $batch->id]);

        if (! $launched) {
            $job = $batch->kind === 'blog' ? \App\Jobs\WriteAiBlogPost::class : \App\Jobs\WriteAiProduct::class;
            foreach ($batch->items()->pluck('id') as $id) {
                $job::dispatch($id);
            }
        }
    }

    /** Primary + secondary keywords from a SeoMeta as a comma string. */
    private function keywordsFor($seoMeta): string
    {
        if (! $seoMeta) {
            return '';
        }

        return collect([$seoMeta->focus_keyword])
            ->merge((array) $seoMeta->secondary_keywords)
            ->filter()
            ->unique()
            ->implode(', ');
    }
}
