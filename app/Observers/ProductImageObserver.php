<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Services\Performance\ImageOptimizer;
use Throwable;

class ProductImageObserver
{
    public function __construct(protected ImageOptimizer $optimizer) {}

    public function saved(ProductImage $image): void
    {
        if (! $image->path) {
            return;
        }

        // Only regenerate when the source path changed and no webp exists yet, or on create.
        if (! $image->wasChanged('path') && $image->webp_path) {
            return;
        }

        try {
            $webpPath = $this->optimizer->toWebp($image->path);
            if ($webpPath && $webpPath !== $image->webp_path) {
                // Avoid an infinite loop by using saveQuietly.
                $image->webp_path = $webpPath;
                $image->saveQuietly();
            }
        } catch (Throwable) {
            // Never let image optimization block a save.
        }
    }
}
