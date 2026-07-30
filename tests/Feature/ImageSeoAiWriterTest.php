<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Services\Seo\ImageSeoRules;
use App\Services\Seo\ImageSeoWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageSeoAiWriterTest extends TestCase
{
    use RefreshDatabase;

    protected function imageFor(string $slug, array $attrs = []): ProductImage
    {
        $product = Product::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug,
            'type' => 'simple', 'price' => 10, 'status' => 'published',
        ]);

        return ProductImage::create(array_merge(
            ['product_id' => $product->id, 'path' => "products/IMG_2026.jpg", 'sort_order' => 0],
            $attrs,
        ));
    }

    protected function fakeAi(array $images): void
    {
        Setting::set('ai.anthropic_api_key', 'k');

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['images' => $images])]],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 300, 'output_tokens' => 100],
        ])]);
    }

    public function test_ai_fills_missing_metadata_and_strips_banned_prefixes(): void
    {
        $image = $this->imageFor('terea-amber');

        $this->fakeAi([[
            'id' => $image->id,
            // Banned prefix must be stripped defensively even if the AI slips.
            'alt' => 'Image of IQOS TEREA Amber pack of 20 heated tobacco sticks, front view',
            'title' => 'IQOS TEREA Amber, 20 sticks pack',
            'caption' => 'Each TEREA Amber pack contains 20 sticks for IQOS ILUMA devices.',
        ]]);

        $updated = app(ImageSeoWriter::class)->generate(collect([$image]), 'anthropic');

        $this->assertSame(1, $updated);
        $image->refresh();
        $this->assertSame('IQOS TEREA Amber pack of 20 heated tobacco sticks, front view', $image->alt);
        $this->assertSame('IQOS TEREA Amber, 20 sticks pack', $image->title);
        $this->assertLessThanOrEqual(ImageSeoRules::CAPTION_MAX, mb_strlen($image->caption));
    }

    public function test_ai_never_overwrites_handwritten_text_unless_asked(): void
    {
        $image = $this->imageFor('terea-sienna', ['alt' => 'Hand-written alt text']);

        $this->fakeAi([[
            'id' => $image->id,
            'alt' => 'AI alt that must not win',
            'title' => 'AI title fills the blank',
        ]]);

        app(ImageSeoWriter::class)->generate(collect([$image]), 'anthropic', overwrite: false);

        $image->refresh();
        $this->assertSame('Hand-written alt text', $image->alt); // preserved
        $this->assertSame('AI title fills the blank', $image->title); // blank → filled
    }

    public function test_rulebook_lint_flags_junk_filenames_and_missing_fields(): void
    {
        $image = $this->imageFor('terea-yellow');

        $issues = implode(' ', ImageSeoRules::lint($image));

        $this->assertStringContainsString('Missing alt text', $issues);
        $this->assertStringContainsString('camera/junk name', $issues);

        $image->update(['alt' => 'TEREA Yellow pack front view', 'title' => 'TEREA Yellow']);
        $image->update(['path' => 'products/terea-yellow.jpg']);

        $this->assertSame([], ImageSeoRules::lint($image->fresh()));
    }

    public function test_seo_rename_moves_the_file_and_keeps_featured_image_in_sync(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/IMG_2026.jpg', 'binary');

        $image = $this->imageFor('terea-bronze');
        $image->product->update(['featured_image' => 'products/IMG_2026.jpg']);

        $newName = $image->fresh()->load('product')->renameToSeoFilename();

        $this->assertSame('terea-bronze.jpg', $newName);
        $this->assertTrue(Storage::disk('public')->exists('products/terea-bronze.jpg'));
        $this->assertFalse(Storage::disk('public')->exists('products/IMG_2026.jpg'));
        $this->assertSame('products/terea-bronze.jpg', $image->fresh()->path);
        $this->assertSame('products/terea-bronze.jpg', $image->product->fresh()->featured_image);
    }
}
