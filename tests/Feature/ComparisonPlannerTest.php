<?php

namespace Tests\Feature;

use App\Models\AiImportBatch;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\BlogTopicIdea;
use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\ComparisonPlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ComparisonPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function seedFlavorFamily(): Attribute
    {
        return Attribute::create(['name' => 'Flavor Family', 'slug' => 'flavor-family', 'type' => 'select']);
    }

    protected function productWithFacet(string $name, Category $category, Attribute $attribute, string $value): Product
    {
        $product = Product::create([
            'name' => $name, 'slug' => str($name)->slug(), 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ]);
        $product->categories()->attach($category->id);

        $attrValue = AttributeValue::firstOrCreate(
            ['attribute_id' => $attribute->id, 'slug' => str($value)->slug()],
            ['value' => $value],
        );
        $product->attributeValues()->attach($attrValue->id);

        return $product;
    }

    public function test_choose_pairs_pairs_products_that_share_a_category_and_differ_on_a_facet(): void
    {
        $category = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $flavor = $this->seedFlavorFamily();

        $yellow = $this->productWithFacet('TEREA Yellow', $category, $flavor, 'Menthol');
        $bronze = $this->productWithFacet('TEREA Bronze', $category, $flavor, 'Regular');

        $pairs = (new ComparisonPlanner)->choosePairs();

        $this->assertCount(1, $pairs);
        $this->assertSame('flavor-family', $pairs[0]['facet']);
        $this->assertEqualsCanonicalizing(
            [$yellow->id, $bronze->id],
            collect($pairs[0]['products'])->pluck('id')->all()
        );
    }

    public function test_choose_pairs_skips_products_sharing_the_same_facet_value(): void
    {
        $category = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $flavor = $this->seedFlavorFamily();

        $this->productWithFacet('TEREA Yellow', $category, $flavor, 'Menthol');
        $this->productWithFacet('TEREA Turquoise', $category, $flavor, 'Menthol');

        $pairs = (new ComparisonPlanner)->choosePairs();

        $this->assertCount(0, $pairs);
    }

    public function test_choose_pairs_returns_empty_when_no_differentiator_attributes_exist(): void
    {
        $category = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        Product::create(['name' => 'A', 'slug' => 'a', 'type' => 'simple', 'price' => 10, 'status' => 'published'])
            ->categories()->attach($category->id);
        Product::create(['name' => 'B', 'slug' => 'b', 'type' => 'simple', 'price' => 10, 'status' => 'published'])
            ->categories()->attach($category->id);

        $pairs = (new ComparisonPlanner)->choosePairs();

        $this->assertCount(0, $pairs);
    }

    public function test_run_writes_angles_and_saves_comparison_ideas(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $category = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $flavor = $this->seedFlavorFamily();
        $yellow = $this->productWithFacet('TEREA Yellow', $category, $flavor, 'Menthol');
        $bronze = $this->productWithFacet('TEREA Bronze', $category, $flavor, 'Regular');

        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['text' => json_encode([
                'ideas' => [[
                    'pair_index' => 0,
                    'title' => 'TEREA Yellow vs Bronze: Which Should You Buy',
                    'primary_keyword' => 'terea yellow vs bronze',
                    'secondary_keywords' => ['yellow or bronze terea', 'terea flavor comparison'],
                    'pain_point' => 'Cannot tell which flavor suits a first-time buyer.',
                    'search_query' => 'terea yellow vs bronze which is better',
                    'angle' => 'Help the reader choose between the two flavors.',
                    'outline' => ['Flavor comparison', 'Cooling and strength', 'Who should pick which', 'Verdict'],
                ]],
            ])]]]),
        ]);

        $batch = AiImportBatch::create([
            'name' => 'Comparison batch', 'kind' => 'comparison_ideas', 'csv_path' => '',
            'prompt' => 'brief', 'provider' => 'anthropic',
        ]);

        $saved = (new ComparisonPlanner)->run($batch);

        $this->assertSame(1, $saved);

        $idea = BlogTopicIdea::first();
        $this->assertNotNull($idea);
        $this->assertSame('comparison', $idea->role);
        $this->assertSame('middle', $idea->funnel_stage);
        $this->assertSame('TEREA Yellow vs Bronze: Which Should You Buy', $idea->title);
        $this->assertEqualsCanonicalizing([$yellow->id, $bronze->id], $idea->compared_product_ids);

        // A comparison must be REQUIRED to link both compared product pages:
        // link_targets is what sendToWriter turns into required_links.
        $this->assertEqualsCanonicalizing([$yellow->url(), $bronze->url()], $idea->link_targets);

        // Planner metadata gives the writer as rich a brief as funnel ideas.
        $this->assertSame(['yellow or bronze terea', 'terea flavor comparison'], $idea->secondary_keywords);
        $this->assertSame('Cannot tell which flavor suits a first-time buyer.', $idea->pain_point);
        $this->assertSame('terea yellow vs bronze which is better', $idea->search_query);
    }

    public function test_canonical_guard_rejects_a_comparison_title_colliding_with_an_existing_post(): void
    {
        Setting::set('ai.anthropic_api_key', 'k');
        $category = Category::create(['name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true]);
        $flavor = $this->seedFlavorFamily();
        $this->productWithFacet('TEREA Yellow', $category, $flavor, 'Menthol');
        $this->productWithFacet('TEREA Bronze', $category, $flavor, 'Regular');

        Post::create([
            'title' => 'TEREA Yellow vs Bronze Comparison', 'slug' => 'existing-compare', 'content' => 'x',
            'status' => 'published', 'author_id' => User::factory()->create()->id,
        ]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['text' => json_encode([
                'ideas' => [[
                    'pair_index' => 0,
                    'title' => 'TEREA Yellow vs Bronze Comparison Guide',
                    'primary_keyword' => 'terea yellow vs bronze',
                    'angle' => 'Angle.',
                    'outline' => ['A', 'B', 'C'],
                ]],
            ])]]]),
        ]);

        $batch = AiImportBatch::create([
            'name' => 'Comparison batch', 'kind' => 'comparison_ideas', 'csv_path' => '',
            'prompt' => 'brief', 'provider' => 'anthropic',
        ]);

        $saved = (new ComparisonPlanner)->run($batch);

        $this->assertSame(0, $saved);
        $this->assertSame(0, BlogTopicIdea::count());
    }
}
