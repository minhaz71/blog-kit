<?php

namespace Tests\Feature;

use App\Console\Commands\FillProductImagesFromDrive;
use App\Models\Product;
use App\Models\Setting;
use App\Services\Ai\DriveImageFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriveProductImageFillTest extends TestCase
{
    use RefreshDatabase;

    /** A fetcher that records who it was asked to fetch and stubs an image. */
    protected function fakeFetcher(array &$calls, array $misses = []): void
    {
        $this->app->instance(DriveImageFetcher::class, new class($calls, $misses) extends DriveImageFetcher
        {
            public function __construct(public array &$calls, public array $misses) {}

            public function fetch(Product $product, string $folder, array $meta = []): bool
            {
                $this->calls[] = $product->id;

                if (in_array($product->id, $this->misses, true)) {
                    return false; // simulate "no matching file in the folder"
                }

                $product->update(['featured_image' => "products/{$product->slug}.jpg"]);

                return true;
            }
        });
    }

    protected function product(string $name, ?string $image = null): Product
    {
        return Product::create([
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'featured_image' => $image,
        ]);
    }

    public function test_fills_only_products_missing_an_image(): void
    {
        Setting::set('ai.google_drive_api_key', 'test-key');
        $missing1 = $this->product('IQOS TEREA Amber');
        $missing2 = $this->product('IQOS TEREA Blue');
        $hasImage = $this->product('IQOS TEREA Bronze', 'products/bronze.jpg');

        $calls = [];
        $this->fakeFetcher($calls);

        $this->artisan('products:fill-images', ['--folder' => 'folder-id'])->assertSuccessful();

        sort($calls);
        $this->assertSame([$missing1->id, $missing2->id], $calls, 'Only image-less products are fetched');
        $this->assertNotNull($missing1->fresh()->featured_image);
        $this->assertSame('products/bronze.jpg', $hasImage->fresh()->featured_image, 'Existing image untouched');
    }

    public function test_override_also_replaces_existing_images(): void
    {
        Setting::set('ai.google_drive_api_key', 'test-key');
        $hasImage = $this->product('IQOS TEREA Bronze', 'products/old.jpg');

        $calls = [];
        $this->fakeFetcher($calls);

        $this->artisan('products:fill-images', ['--folder' => 'f', '--override' => true])->assertSuccessful();

        $this->assertSame([$hasImage->id], $calls);
    }

    public function test_targets_only_the_given_ids(): void
    {
        Setting::set('ai.google_drive_api_key', 'test-key');
        $a = $this->product('Amber');
        $this->product('Blue'); // also missing, but not selected

        $calls = [];
        $this->fakeFetcher($calls);

        $this->artisan('products:fill-images', ['--folder' => 'f', '--ids' => (string) $a->id])->assertSuccessful();

        $this->assertSame([$a->id], $calls);
    }

    public function test_reports_status_and_counts_no_matches(): void
    {
        Setting::set('ai.google_drive_api_key', 'test-key');
        $ok = $this->product('Amber');
        $noMatch = $this->product('Zzzz Unknown');

        $calls = [];
        $this->fakeFetcher($calls, misses: [$noMatch->id]);

        $this->artisan('products:fill-images', ['--folder' => 'f'])->assertSuccessful();

        $status = FillProductImagesFromDrive::status();
        $this->assertSame('done', $status['state']);
        $this->assertSame(1, $status['matched']);
        $this->assertSame(1, $status['missing']);
        // The folder is remembered for next time.
        $this->assertSame('f', Setting::get('catalog.drive_image_folder'));
    }

    public function test_fails_clearly_without_api_key(): void
    {
        Setting::set('ai.google_drive_api_key', '');
        $this->product('Amber');

        $this->artisan('products:fill-images', ['--folder' => 'f'])->assertFailed();

        $this->assertSame('failed', FillProductImagesFromDrive::status()['state']);
    }

    public function test_fails_clearly_without_folder(): void
    {
        Setting::set('ai.google_drive_api_key', 'k');

        $this->artisan('products:fill-images')->assertFailed();
    }
}
