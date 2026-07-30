<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Ai\DriveImageFetcher;
use App\Services\Ai\SampleCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiSampleCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_csv_contains_terea_products_without_image_columns(): void
    {
        $csv = SampleCsv::content();
        // The file opens with a "#" category reference block (ignored on
        // upload); the data starts at the first non-comment line.
        $lines = array_values(array_filter(
            explode("\n", trim($csv)),
            fn ($l) => trim($l) !== '' && ! str_starts_with(trim($l), '#'),
        ));

        $this->assertStringContainsString('name,regular_price,sale_price', $lines[0] ?? '');

        // Images are NOT part of the CSV — they come from the batch's Drive folder.
        $this->assertStringNotContainsString('image_link', $lines[0] ?? '');
        $this->assertStringNotContainsString('drive.google.com', $csv);

        // Header + six TEREA products — enough for the minimum-2 rule.
        $this->assertCount(7, $lines);
        $this->assertStringContainsString('IQOS TEREA Amber', $csv);
        $this->assertStringContainsString('IQOS TEREA Purple Wave', $csv);
        $this->assertStringContainsString('IQOS ILUMA', $csv);

        // Round-trip: the exact same file parses through the importer's header logic.
        $headers = array_map(
            fn ($h) => str_replace('product_', '', str_replace(' ', '_', strtolower(trim($h)))),
            str_getcsv($lines[0]),
        );
        $this->assertContains('name', $headers);
        $this->assertContains('keywords', $headers);
    }

    /** A real 1×1 PNG — the fetcher now validates actual image bytes. */
    protected function tinyPng(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    public function test_per_row_drive_image_link_downloads_without_api_key(): void
    {
        Storage::fake('public');
        Http::fake([
            'drive.google.com/uc*' => Http::response($this->tinyPng(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $product = Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 32, 'status' => 'published',
        ]);

        $ok = (new DriveImageFetcher)->fetchFromLink(
            $product,
            'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz12345/view',
            ['alt' => 'TEREA Amber pack', 'title' => 'TEREA Amber', 'caption' => 'Amber pack shot'],
        );

        $this->assertTrue($ok);
        $product->refresh();
        $this->assertNotNull($product->featured_image);
        // Deterministic permalink: Drive share links carry no filename, so
        // the product slug becomes the slug — never a random suffix.
        $this->assertSame('products/terea-amber.jpg', $product->featured_image);
        $image = $product->images()->first();
        $this->assertSame('TEREA Amber pack', $image->alt);
        // SEO metadata the AI writes is stored, not discarded.
        $this->assertSame('TEREA Amber', $image->title);
        $this->assertSame('Amber pack shot', $image->caption);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'id=1AbCdEfGhIjKlMnOpQrStUvWxYz12345'));
    }

    public function test_html_page_is_never_saved_as_a_product_image(): void
    {
        // Google Drive returns an HTML confirmation page for large files —
        // it must be rejected, never stored as {slug}.jpg.
        Storage::fake('public');
        Http::fake([
            'drive.google.com/uc*' => Http::response('<html><body>Virus scan warning…</body></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);

        $product = Product::create([
            'name' => 'TEREA Sienna', 'slug' => 'terea-sienna', 'type' => 'simple',
            'price' => 32, 'status' => 'published',
        ]);

        try {
            (new DriveImageFetcher)->fetchFromLink($product, 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz12345/view');
            $this->fail('Expected the HTML payload to be rejected.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('not an image', $e->getMessage());
        }

        $this->assertNull($product->fresh()->featured_image);
        $this->assertSame(0, $product->images()->count());
    }

    public function test_drive_file_id_extraction_patterns(): void
    {
        $this->assertSame('abc123DEF456ghi', DriveImageFetcher::driveFileId('https://drive.google.com/file/d/abc123DEF456ghi/view?usp=sharing'));
        $this->assertSame('abc123DEF456ghi', DriveImageFetcher::driveFileId('https://drive.google.com/open?id=abc123DEF456ghi'));
        $this->assertNull(DriveImageFetcher::driveFileId('https://example.com/image.jpg'));
    }

    public function test_direct_image_url_also_works(): void
    {
        Storage::fake('public');
        Http::fake(['example.com/*' => Http::response($this->tinyPng(), 200, ['Content-Type' => 'image/png'])]);

        $product = Product::create([
            'name' => 'TEREA Yellow', 'slug' => 'terea-yellow', 'type' => 'simple',
            'price' => 32, 'status' => 'published',
        ]);

        $this->assertTrue((new DriveImageFetcher)->fetchFromLink($product, 'https://example.com/terea-yellow.png'));
        $this->assertStringEndsWith('.png', $product->fresh()->featured_image);
    }
}
