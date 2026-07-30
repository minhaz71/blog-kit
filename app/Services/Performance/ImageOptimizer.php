<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * WebP generation for uploaded images. Reads from and writes to the
 * public disk. Returns the WebP path (relative to the disk) or null.
 */
class ImageOptimizer
{
    protected string $disk = 'public';

    protected int $webpQuality = 80;

    public function toWebp(string $sourcePath): ?string
    {
        $sourcePath = ltrim($sourcePath, '/');
        if (! Storage::disk($this->disk)->exists($sourcePath)) {
            return null;
        }

        // Skip if already WebP.
        if (str_ends_with(strtolower($sourcePath), '.webp')) {
            return $sourcePath;
        }

        $targetPath = preg_replace('/\.[a-zA-Z0-9]+$/', '.webp', $sourcePath);
        if (! $targetPath) {
            return null;
        }

        try {
            $bytes = Storage::disk($this->disk)->get($sourcePath);
            $manager = new ImageManager(new Driver);
            $img = $manager->read($bytes);
            $webpBytes = (string) $img->toWebp($this->webpQuality);
            Storage::disk($this->disk)->put($targetPath, $webpBytes);

            return $targetPath;
        } catch (Throwable) {
            return null;
        }
    }
}
