<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return asset('storage/'.ltrim($this->path, '/'));
    }

    public function webpUrl(): ?string
    {
        return $this->webp_path ? asset('storage/'.ltrim($this->webp_path, '/')) : null;
    }

    /** Alt text falls back to the product name for SEO. */
    public function altText(): string
    {
        return $this->alt ?: ($this->product?->name ?? '');
    }

    /** Title attribute falls back to alt text, then the product name. */
    public function titleText(): string
    {
        return $this->title ?: $this->altText();
    }

    /**
     * Rename the stored file (and its webp twin) to an SEO-friendly,
     * product-slug-based filename and update the paths. Returns the new
     * basename, or null when the file is already well-named / missing.
     */
    public function renameToSeoFilename(): ?string
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $position = (int) $this->sort_order;
        $newName = \App\Services\Seo\ImageSeoRules::seoFilename($this, $position);
        $directory = trim(dirname($this->path), '/.') ?: 'products';
        $newPath = $directory.'/'.$newName;

        if ($newPath === $this->path || ! $disk->exists($this->path)) {
            return null;
        }

        // Never overwrite another file — suffix until free.
        $i = 1;
        while ($disk->exists($newPath)) {
            $newPath = $directory.'/'.pathinfo($newName, PATHINFO_FILENAME).'-'.$i++.'.'.pathinfo($newName, PATHINFO_EXTENSION);
        }

        $disk->move($this->path, $newPath);

        // The product's featured image often IS this file — keep it valid.
        if ($this->product && $this->product->featured_image === $this->path) {
            $this->product->update(['featured_image' => $newPath]);
        }

        $updates = ['path' => $newPath];

        if ($this->webp_path && $disk->exists($this->webp_path)) {
            $newWebp = $directory.'/'.pathinfo($newPath, PATHINFO_FILENAME).'.webp';

            if ($newWebp !== $this->webp_path && ! $disk->exists($newWebp)) {
                $disk->move($this->webp_path, $newWebp);
                $updates['webp_path'] = $newWebp;
            }
        }

        $this->update($updates);

        return basename($newPath);
    }
}
