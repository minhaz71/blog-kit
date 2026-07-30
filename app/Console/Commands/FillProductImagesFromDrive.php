<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\User;
use App\Services\Ai\DriveImageFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Fills in missing product images from a shared Google Drive folder. For each
 * target product it reuses DriveImageFetcher's filename→product name matching
 * to find the most relevant photo in the folder, validates it's a real image,
 * and attaches it as the featured + gallery image with alt text.
 *
 * Runs detached (multi-minute for many downloads); progress is published to a
 * cache status the admin polls, and a database notification lands when done.
 * No AI — pure name matching, so it never spends LLM credit.
 */
class FillProductImagesFromDrive extends Command
{
    protected $signature = 'products:fill-images
        {--folder= : Google Drive folder link or ID (shared "anyone with the link")}
        {--ids= : Comma-separated product IDs to target (default: every product missing an image)}
        {--override : Also replace images on products that already have one}
        {--user= : Admin user id to notify when finished}';

    protected $description = 'Match and attach product images from a shared Google Drive folder (no AI)';

    public const STATUS_KEY = 'product-image-fill:status';

    public static function status(): ?array
    {
        return Cache::get(self::STATUS_KEY);
    }

    public static function clearStatus(): void
    {
        Cache::forget(self::STATUS_KEY);
    }

    protected function setStatus(string $state, string $message, array $extra = []): void
    {
        Cache::put(self::STATUS_KEY, array_merge([
            'state' => $state,          // running | done | failed
            'message' => $message,
            'at' => now()->toDateTimeString(),
        ], $extra), now()->addHour());
    }

    public function handle(DriveImageFetcher $fetcher): int
    {
        $folder = trim((string) $this->option('folder'));

        if ($folder === '') {
            $this->setStatus('failed', 'No Google Drive folder was provided.');
            $this->error('A --folder link or ID is required.');

            return self::FAILURE;
        }

        if ((string) setting('ai.google_drive_api_key') === '') {
            $this->setStatus('failed', 'No Google Drive API key is configured (Settings → AI settings).');
            $this->error('Missing Drive API key.');

            return self::FAILURE;
        }

        // Remember the folder so the next run pre-fills it.
        \App\Models\Setting::set('catalog.drive_image_folder', $folder);

        $products = $this->targets();
        $total = $products->count();

        if ($total === 0) {
            $this->setStatus('done', 'Nothing to do — no matching products without an image.', ['matched' => 0, 'missing' => 0, 'failed' => 0, 'total' => 0]);
            $this->info('No target products.');

            return self::SUCCESS;
        }

        $this->setStatus('running', "Starting — 0/{$total} products processed…", ['total' => $total, 'matched' => 0, 'missing' => 0, 'failed' => 0]);

        $matched = $noMatch = $failed = $done = 0;

        foreach ($products as $product) {
            try {
                if ($fetcher->fetch($product, $folder, ['alt' => $product->name, 'title' => $product->name])) {
                    $matched++;
                    $this->info("✓ {$product->name}");
                } else {
                    $noMatch++;
                    $this->warn("• no match for {$product->name}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("✗ {$product->name}: ".$e->getMessage());
            }

            $done++;
            $this->setStatus('running', "Processed {$done}/{$total} — {$matched} matched, {$noMatch} no match, {$failed} error(s).",
                ['total' => $total, 'matched' => $matched, 'missing' => $noMatch, 'failed' => $failed]);
        }

        $summary = "{$matched} image(s) added, {$noMatch} with no folder match, {$failed} error(s) — out of {$total} product(s).";
        $this->setStatus('done', $summary, ['total' => $total, 'matched' => $matched, 'missing' => $noMatch, 'failed' => $failed]);
        $this->info($summary);

        $this->notifyOwner($summary);

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int,Product> */
    protected function targets(): \Illuminate\Support\Collection
    {
        $query = Product::query()->whereNull('deleted_at');

        if (($ids = trim((string) $this->option('ids'))) !== '') {
            $query->whereIn('id', array_filter(array_map('intval', explode(',', $ids))));
        }

        // Unless overriding, only products with NO featured image and NO gallery.
        if (! $this->option('override')) {
            $query->where(function ($q): void {
                $q->whereNull('featured_image')->orWhere('featured_image', '');
            })->whereDoesntHave('images');
        }

        return $query->orderBy('id')->get();
    }

    protected function notifyOwner(string $summary): void
    {
        $userId = (int) $this->option('user');

        if ($userId <= 0 || ! \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            return;
        }

        try {
            \Filament\Notifications\Notification::make()
                ->title('Drive image fill finished')
                ->body($summary)
                ->success()
                ->sendToDatabase($user);
        } catch (\Throwable) {
            // Notification is best-effort; the cache status is the source of truth.
        }
    }
}
