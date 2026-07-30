<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Services\Ai\DriveImageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Folder-based image matching: the whole Drive folder is listed and each
 * product gets the image whose FILENAME best matches its name — partial
 * names like "amber kazakhstan.jpg" must match "IQOS Terea Amber
 * Kazakhstan", and a file for a different variant must never win.
 */
class AiDriveImageMatchingTest extends TestCase
{
    use RefreshDatabase;

    protected function tinyPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    /** Fake Drive: files.list returns $files; any file download returns a PNG. */
    protected function fakeDrive(array $files): void
    {
        Setting::set('ai.google_drive_api_key', 'drive-key');
        Storage::fake('public');

        Http::fake(function ($request) use ($files) {
            if (str_contains($request->url(), 'alt=media')) {
                return Http::response($this->tinyPng(), 200, ['Content-Type' => 'image/png']);
            }

            return Http::response(['files' => $files]);
        });
    }

    public function test_partial_filename_matches_the_full_product_name(): void
    {
        // Filenames use only part of the product name — the real-world case.
        $this->fakeDrive([
            ['id' => 'f-sienna', 'name' => 'terea sienna.png', 'mimeType' => 'image/png'],
            ['id' => 'f-amber-kz', 'name' => 'amber kazakhstan.jpg', 'mimeType' => 'image/jpeg'],
            ['id' => 'f-amber', 'name' => 'amber.jpg', 'mimeType' => 'image/jpeg'],
        ]);

        $product = Product::create([
            'name' => 'IQOS Terea Amber Kazakhstan', 'slug' => 'iqos-terea-amber-kazakhstan',
            'type' => 'simple', 'price' => 30, 'status' => 'published',
        ]);

        $this->assertTrue((new DriveImageFetcher)->fetch($product, 'folder-abc', ['alt' => 'Amber KZ pack']));

        // The two-token file beats the one-token file; the sienna file loses.
        $downloaded = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]->url())
            ->first(fn ($url) => str_contains($url, 'alt=media'));
        $this->assertStringContainsString('f-amber-kz', $downloaded);

        $this->assertNotNull($product->fresh()->featured_image);
        $this->assertSame('Amber KZ pack', $product->images()->first()->alt);
    }

    public function test_no_related_filename_means_no_image_not_a_wrong_one(): void
    {
        $this->fakeDrive([
            ['id' => 'f-1', 'name' => 'terea sienna.png', 'mimeType' => 'image/png'],
            ['id' => 'f-2', 'name' => 'bright wave.jpg', 'mimeType' => 'image/jpeg'],
        ]);

        $product = Product::create([
            'name' => 'IQOS Terea Ruby Regular', 'slug' => 'iqos-terea-ruby-regular',
            'type' => 'simple', 'price' => 30, 'status' => 'published',
        ]);

        $this->assertFalse((new DriveImageFetcher)->fetch($product, 'folder-def'));
        $this->assertNull($product->fresh()->featured_image);
    }

    public function test_variant_file_with_foreign_tokens_does_not_win(): void
    {
        // "amber blue special edition.jpg" is for a DIFFERENT variant — only
        // 1 of its 4 tokens matches "Terea Amber" (precision 0.25), so it is
        // rejected outright and the exact-variant file wins.
        $this->fakeDrive([
            ['id' => 'f-blue', 'name' => 'amber blue special edition.jpg', 'mimeType' => 'image/jpeg'],
            ['id' => 'f-amber', 'name' => 'terea amber.jpg', 'mimeType' => 'image/jpeg'],
        ]);

        $product = Product::create([
            'name' => 'IQOS Terea Amber', 'slug' => 'iqos-terea-amber-x',
            'type' => 'simple', 'price' => 30, 'status' => 'published',
        ]);

        $this->assertTrue((new DriveImageFetcher)->fetch($product, 'folder-ghi'));

        $downloaded = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]->url())
            ->first(fn ($url) => str_contains($url, 'alt=media'));
        $this->assertStringContainsString('f-amber', $downloaded);
    }

    public function test_permalink_comes_from_the_original_drive_filename(): void
    {
        $this->fakeDrive([
            ['id' => 'f-kz', 'name' => 'Terea Kazakhstan Amber.JPG', 'mimeType' => 'image/jpeg'],
        ]);

        $product = Product::create([
            'name' => 'IQOS Terea Amber Kazakhstan', 'slug' => 'iqos-terea-amber-kazakhstan',
            'type' => 'simple', 'price' => 30, 'status' => 'published',
        ]);

        $this->assertTrue((new DriveImageFetcher)->fetch($product, 'folder-names'));

        // "Terea Kazakhstan Amber.JPG" → terea-kazakhstan-amber.png (its
        // slug, extension from actual content type) — never a random name.
        $path = $product->images()->first()->path;
        $this->assertSame('products/terea-kazakhstan-amber.png', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));

        // Same name again → deterministic -2 suffix, no overwrite.
        $second = Product::create([
            'name' => 'Terea Amber Kazakhstan Twin', 'slug' => 'terea-amber-kazakhstan-twin',
            'type' => 'simple', 'price' => 30, 'status' => 'published',
        ]);
        (new DriveImageFetcher)->fetch($second, 'folder-names');
        $this->assertSame('products/terea-kazakhstan-amber-2.png', $second->images()->first()->path);
    }

    public function test_folder_listing_is_cached_across_products_in_a_batch(): void
    {
        $this->fakeDrive([
            ['id' => 'f-a', 'name' => 'terea amber.jpg', 'mimeType' => 'image/jpeg'],
            ['id' => 'f-s', 'name' => 'terea sienna.jpg', 'mimeType' => 'image/jpeg'],
        ]);

        $fetcher = new DriveImageFetcher;

        $a = Product::create(['name' => 'Terea Amber', 'slug' => 'ta', 'type' => 'simple', 'price' => 1, 'status' => 'published']);
        $s = Product::create(['name' => 'Terea Sienna', 'slug' => 'ts', 'type' => 'simple', 'price' => 1, 'status' => 'published']);

        $this->assertTrue($fetcher->fetch($a, 'folder-jkl'));
        $this->assertTrue($fetcher->fetch($s, 'folder-jkl'));

        // 2 downloads + only ONE files.list call (second product hits the
        // cache). Non-Drive requests (product observers) are ignored.
        $listCalls = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]->url())
            ->filter(fn ($url) => str_contains($url, 'googleapis.com/drive/v3/files') && ! str_contains($url, 'alt=media'))
            ->count();
        $this->assertSame(1, $listCalls);
    }

    public function test_recurses_into_subfolders_to_find_a_match(): void
    {
        Setting::set('ai.google_drive_api_key', 'drive-key');
        Storage::fake('public');

        // Root folder holds only a subfolder; the matching image lives INSIDE
        // the subfolder. Without recursion this returns no image.
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'alt=media')) {
                return Http::response($this->tinyPng(), 200, ['Content-Type' => 'image/png']);
            }

            // Branch on which folder is being listed (the `q` names its parent).
            if (str_contains(urldecode($url), "'root-folder' in parents")) {
                return Http::response(['files' => [
                    ['id' => 'sub-1', 'name' => 'Kazakhstan', 'mimeType' => 'application/vnd.google-apps.folder'],
                ]]);
            }

            if (str_contains(urldecode($url), "'sub-1' in parents")) {
                return Http::response(['files' => [
                    ['id' => 'f-amber-kz', 'name' => 'terea amber kazakhstan.jpg', 'mimeType' => 'image/jpeg'],
                ]]);
            }

            return Http::response(['files' => []]);
        });

        $product = Product::create([
            'name' => 'IQOS Terea Amber Kazakhstan', 'slug' => 'iqos-terea-amber-kz-sub',
            'type' => 'simple', 'price' => 30, 'status' => 'published',
        ]);

        $this->assertTrue((new DriveImageFetcher)->fetch($product, 'root-folder'));

        $downloaded = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]->url())
            ->first(fn ($url) => str_contains($url, 'alt=media'));
        $this->assertStringContainsString('f-amber-kz', $downloaded);
        $this->assertNotNull($product->fresh()->featured_image);
    }

    public function test_health_check_reports_missing_key_and_folder_visibility(): void
    {
        [$ok, $message] = DriveImageFetcher::healthCheck();
        $this->assertFalse($ok);
        $this->assertStringContainsString('No Google Drive API key', $message);

        $this->fakeDrive([['id' => 'f-1', 'name' => 'terea amber.jpg', 'mimeType' => 'image/jpeg']]);

        [$ok, $message] = DriveImageFetcher::healthCheck('https://drive.google.com/drive/folders/folder-xyz');
        $this->assertTrue($ok);
        $this->assertStringContainsString('1 image(s)', $message);
    }
}
