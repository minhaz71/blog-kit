<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class MediaSyncFeaturedCommand extends Command
{
    protected $signature = 'media:sync-featured';

    protected $description = 'Backfill media records for product featured images so they are editable in the Media library / Image SEO tools';

    public function handle(): int
    {
        $created = 0;

        Product::query()
            ->whereNotNull('featured_image')
            ->where('featured_image', '!=', '')
            ->with('images:id,product_id,path')
            ->chunkById(200, function ($products) use (&$created) {
                foreach ($products as $product) {
                    if (! $product->images->contains('path', $product->featured_image)) {
                        $product->images()->create([
                            'path' => $product->featured_image,
                            'alt' => null,
                            'sort_order' => 0,
                        ]);
                        $created++;
                    }
                }
            });

        $this->info("Created {$created} media record(s) for featured images.");

        return self::SUCCESS;
    }
}
