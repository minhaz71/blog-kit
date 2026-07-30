<?php

namespace Tests\Feature;

use App\Jobs\StartAiImportBatch;
use App\Jobs\WriteAiProduct;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\Category;
use App\Models\Setting;
use App\Services\Ai\ProductWriter;
use App\Services\Ai\SampleCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * CSV category assignment: names auto-create and attach, category_id pins
 * to exact existing categories, and the writer receives the mother-chain
 * context so copy fits the range.
 */
class CsvCategoryAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeWriter(): void
    {
        Setting::set('ai.anthropic_api_key', 'test-key');

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => json_encode([
                    'short_description_html' => '<p>x</p>',
                    'description_html' => '<h2>About</h2><p>Copy.</p>',
                    'css' => '', 'suggested_price' => 30,
                    'meta_title' => 'T', 'meta_description' => 'D', 'focus_keyword' => 'k',
                    'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
                ])]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);
    }

    protected function runBatch(string $csv): AiImportBatch
    {
        $this->fakeWriter();
        Storage::disk('local')->put('ai-imports/cats.csv', $csv);

        $batch = AiImportBatch::create([
            'name' => 'Cats', 'csv_path' => 'ai-imports/cats.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic',
            'require_approval' => false, 'publish_mode' => 'publish', 'price_mode' => 'csv',
        ]);

        (new StartAiImportBatch($batch))->handle();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        return $batch->refresh();
    }

    public function test_category_names_auto_create_and_assign(): void
    {
        $batch = $this->runBatch(
            "name,regular_price,category\nTEREA Amber,32,Heated Tobacco|TEREA Japan\nTEREA Sienna,32,Heated Tobacco\n"
        );

        $product = $batch->items()->first()->product;

        $this->assertNotNull($product);
        $this->assertEqualsCanonicalizing(
            ['Heated Tobacco', 'TEREA Japan'],
            $product->categories->pluck('name')->all(),
        );
    }

    public function test_category_id_pins_exact_category_and_warns_on_unknown(): void
    {
        $exact = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $missingId = $exact->id + 999;

        $batch = $this->runBatch(
            "name,regular_price,category_id\nTEREA Amber,32,{$exact->id}|{$missingId}\nTEREA Sienna,32,{$exact->id}\n"
        );

        $product = $batch->items()->first()->product;

        $this->assertNotNull($product);
        $this->assertSame([$exact->id], $product->categories->pluck('id')->all());

        // The unknown ID never creates a category — it warns instead.
        $this->assertSame(1, Category::count());
        $this->assertTrue(
            AiActivityLog::where('batch_id', $batch->id)
                ->where('message', 'like', "%category_id {$missingId} does not exist%")
                ->exists(),
        );
    }

    public function test_writer_receives_mother_chain_category_context(): void
    {
        $parent = Category::create(['name' => 'IQOS TEREA UAE', 'slug' => 'iqos-terea-uae', 'is_active' => true]);
        $child = Category::create([
            'name' => 'TEREA Japan', 'slug' => 'terea-japan', 'is_active' => true,
            'parent_id' => $parent->id,
            'content_block' => '<p>Japanese-market TEREA sticks, prized for refined blends.</p>',
        ]);

        // Resolves by name…
        $context = ProductWriter::categoryContextFor(['category' => 'TEREA Japan']);
        $this->assertStringContainsString('CATEGORY CONTEXT', $context);
        $this->assertStringContainsString('IQOS TEREA UAE > TEREA Japan', $context);
        $this->assertStringContainsString('Japanese-market TEREA sticks', $context);

        // …and by exact ID.
        $byId = ProductWriter::categoryContextFor(['category_id' => (string) $child->id]);
        $this->assertStringContainsString('IQOS TEREA UAE > TEREA Japan', $byId);

        // Unknown categories produce no block (nothing invented).
        $this->assertSame('', ProductWriter::categoryContextFor(['category' => 'Nonexistent Range']));
        $this->assertSame('', ProductWriter::categoryContextFor([]));
    }

    /** The real header is the first non-comment line. */
    protected function csvHeaderLine(string $csv): string
    {
        foreach (preg_split('/\r?\n/', $csv) as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '' && ! str_starts_with($trimmed, '#')) {
                return $line;
            }
        }

        return '';
    }

    public function test_sample_csv_has_category_and_category_id_columns(): void
    {
        $header = $this->csvHeaderLine(SampleCsv::content());

        $this->assertStringContainsString('category', $header);
        $this->assertStringContainsString('category_id', $header);
        // With no categories, the sample demonstrates multi-assignment by name.
        $this->assertStringContainsString('Heated Tobacco|TEREA UAE', SampleCsv::content());
    }

    public function test_sample_csv_lists_active_categories_with_ids(): void
    {
        $parent = Category::create(['name' => 'Heated Tobacco', 'slug' => 'heated-tobacco', 'is_active' => true]);
        $child = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true, 'parent_id' => $parent->id]);
        Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $csv = SampleCsv::content();

        // Reference block shows each ACTIVE category id + name (+ parent).
        $this->assertStringContainsString('# '.$parent->id.' | Heated Tobacco', $csv);
        $this->assertStringContainsString('# '.$child->id.' | TEREA UAE  (under Heated Tobacco)', $csv);
        $this->assertStringNotContainsString('Hidden', $csv); // inactive excluded

        // Example rows now carry a REAL category_id the admin can keep as-is.
        $header = $this->csvHeaderLine($csv);
        $this->assertStringContainsString('category_id', $header);
        $this->assertMatchesRegularExpression('/,'.$parent->id.',/', $csv);
    }

    public function test_parser_skips_the_category_reference_comment_block(): void
    {
        $csv = "# ===== YOUR CATEGORIES =====\n"
            ."# 1 | Heated Tobacco\n"
            ."# keep or delete these lines\n"
            ."\n"
            ."name,regular_price,category\n"
            ."TEREA Amber,32,Heated Tobacco\n"
            ."TEREA Sienna,32,Heated Tobacco\n";

        $batch = $this->runBatch($csv);

        // The # block + blank line are ignored; both products parse.
        $this->assertSame(2, $batch->total_items);
        $this->assertSame('TEREA Amber', $batch->items()->orderBy('id')->first()->row['name']);
    }
}
