<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoEditor;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoBulkEditTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $slug): Product
    {
        return Product::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug,
            'type' => 'simple', 'price' => 10, 'status' => 'published',
        ]);
    }

    public function test_seo_csv_import_updates_products_by_slug(): void
    {
        $amber = $this->product('terea-amber');
        $this->product('terea-sienna');

        $csv = implode("\n", [
            'slug,name,seo_title,meta_description,focus_keyword,canonical_url,noindex',
            'terea-amber,Terea Amber,"Buy TEREA Amber UAE","Rich roasted tobacco, fast delivery.","terea amber uae",,0',
            'unknown-slug,Nope,"x","y","z",,0',
        ]);

        $path = storage_path('app/seo-import-test.csv');
        file_put_contents($path, $csv);

        $updated = SeoEditor::importCsv($path);
        @unlink($path);

        $this->assertSame(1, $updated); // unknown slug skipped

        $meta = $amber->fresh()->seoMeta;
        $this->assertSame('Buy TEREA Amber UAE', $meta->title);
        $this->assertSame('Rich roasted tobacco, fast delivery.', $meta->description);
        $this->assertSame('terea amber uae', $meta->focus_keyword);
        $this->assertFalse((bool) $meta->noindex);
    }
}
