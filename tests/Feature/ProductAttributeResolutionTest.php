<?php

namespace Tests\Feature;

use App\Jobs\StartAiImportBatch;
use App\Jobs\WriteAiProduct;
use App\Models\AiActivityLog;
use App\Models\AiImportBatch;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductAttributeResolutionTest extends TestCase
{
    use RefreshDatabase;

    protected function seedTaxonomy(): Attribute
    {
        $flavor = Attribute::create(['name' => 'Flavor Family', 'slug' => 'flavor-family', 'type' => 'select']);
        AttributeValue::create(['attribute_id' => $flavor->id, 'value' => 'Menthol', 'slug' => 'menthol']);

        Attribute::create(['name' => 'Cooling Level', 'slug' => 'cooling-level', 'type' => 'select']);

        return $flavor;
    }

    protected function fakeWriterWithAttributes(array $attributes): void
    {
        Setting::set('ai.anthropic_api_key', 'test-key');

        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['text' => json_encode([
                    'short_description_html' => '<p>A great TEREA flavor.</p>',
                    'description_html' => '<div><h2>Flavor</h2><p>Smooth and cool.</p></div>',
                    'css' => '',
                    'suggested_price' => 29.0,
                    'meta_title' => 'TEREA Test', 'meta_description' => 'Buy TEREA Test online in the UAE.',
                    'focus_keyword' => 'terea test',
                    'image_alt' => 'a', 'image_title' => 't', 'image_caption' => 'c',
                    'attributes' => $attributes,
                ])]]])
                ->whenEmpty(Http::response(['content' => [['text' => '{"approved": true}']]])),
        ]);
    }

    public function test_exact_match_attribute_value_attaches_to_the_product(): void
    {
        $this->seedTaxonomy();
        $this->fakeWriterWithAttributes(['flavor_family' => 'Menthol']);

        Storage::disk('local')->put('ai-imports/attr.csv', "name,regular_price\nTerea Test,25\nOther Thing,20\n");
        $batch = AiImportBatch::create([
            'name' => 'Attr batch', 'csv_path' => 'ai-imports/attr.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic', 'require_approval' => false, 'publish_mode' => 'publish',
        ]);

        (new StartAiImportBatch($batch))->handle();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $product = $batch->items()->first()->product;
        $this->assertNotNull($product);

        $flavorValue = AttributeValue::where('slug', 'menthol')->first();
        $this->assertTrue($product->attributeValues->contains($flavorValue));
        $this->assertFalse($flavorValue->fresh()->needs_review);
    }

    public function test_novel_attribute_value_is_created_and_flagged_for_review(): void
    {
        $this->seedTaxonomy();
        $this->fakeWriterWithAttributes(['flavor_family' => 'Iced Fruit Blend']);

        Storage::disk('local')->put('ai-imports/attr2.csv', "name,regular_price\nTerea Test,25\nOther Thing,20\n");
        $batch = AiImportBatch::create([
            'name' => 'Attr batch 2', 'csv_path' => 'ai-imports/attr2.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic', 'require_approval' => false, 'publish_mode' => 'publish',
        ]);

        (new StartAiImportBatch($batch))->handle();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $product = $batch->items()->first()->product;
        $this->assertNotNull($product);

        $novelValue = AttributeValue::where('slug', 'iced-fruit-blend')->first();
        $this->assertNotNull($novelValue);
        $this->assertTrue($novelValue->needs_review);
        $this->assertTrue($product->attributeValues->contains($novelValue));

        $this->assertTrue(
            AiActivityLog::where('batch_id', $batch->id)->where('stage', 'attributes')->where('level', 'warning')->exists()
        );
    }

    public function test_unknown_attribute_key_is_ignored_without_creating_a_new_attribute(): void
    {
        $this->seedTaxonomy();
        $this->fakeWriterWithAttributes(['not_a_real_facet' => 'Something']);

        Storage::disk('local')->put('ai-imports/attr3.csv', "name,regular_price\nTerea Test,25\nOther Thing,20\n");
        $batch = AiImportBatch::create([
            'name' => 'Attr batch 3', 'csv_path' => 'ai-imports/attr3.csv', 'prompt' => 'p',
            'provider' => 'anthropic', 'reviewer_provider' => 'anthropic', 'require_approval' => false, 'publish_mode' => 'publish',
        ]);

        (new StartAiImportBatch($batch))->handle();
        (new WriteAiProduct($batch->items()->first()->id))->handle();

        $this->assertNotNull($batch->items()->first()->product);
        $this->assertSame(2, Attribute::count()); // still only the two seeded facets
    }
}
