<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Product;
use App\Models\SearchLog;
use App\Models\Setting;
use App\Services\Search\ProductSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjaxSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $name, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'name' => $name, 'slug' => str($name)->slug(), 'type' => 'simple',
            'price' => 220, 'status' => 'published', 'stock_status' => 'in_stock', 'visibility' => 'visible',
        ], $overrides));
    }

    public function test_suggest_returns_ranked_json_results(): void
    {
        $this->product('TEREA Amber Carton');
        $this->product('TEREA Sienna Carton');
        $this->product('Random Widget');

        $res = $this->getJson('/search/suggest?q=terea')->assertOk();

        $res->assertJson(['enabled' => true, 'total' => 2]);
        $names = collect($res->json('results'))->pluck('name');
        $this->assertTrue($names->contains('TEREA Amber Carton'));
        $this->assertFalse($names->contains('Random Widget'));
        // Each result carries what the dropdown renders.
        $first = $res->json('results.0');
        $this->assertArrayHasKey('url', $first);
        $this->assertArrayHasKey('price', $first);
    }

    public function test_exact_and_prefix_matches_rank_first(): void
    {
        $this->product('Blue Menthol Sticks');   // mid-word "menthol"
        $this->product('Menthol Fresh');          // prefix "menthol"
        $this->product('Menthol');                // exact

        $names = collect($this->getJson('/search/suggest?q=menthol')->json('results'))->pluck('name');

        $this->assertSame('Menthol', $names->first());        // exact wins
        $this->assertSame('Menthol Fresh', $names->get(1));   // prefix next
    }

    public function test_respects_min_chars(): void
    {
        Setting::set('search.min_chars', 3);
        $this->product('TEREA Amber');

        $this->getJson('/search/suggest?q=te')->assertOk()->assertJson(['total' => 0]);
        $this->getJson('/search/suggest?q=ter')->assertOk()->assertJson(['total' => 1]);
    }

    public function test_disabled_returns_empty(): void
    {
        Setting::set('search.ajax_enabled', false);
        $this->product('TEREA Amber');

        $this->getJson('/search/suggest?q=terea')->assertOk()->assertJson(['enabled' => false, 'total' => 0]);
    }

    public function test_hidden_and_draft_products_never_surface(): void
    {
        $this->product('Visible Terea');
        $this->product('Draft Terea', ['status' => 'draft']);
        $this->product('Hidden Terea', ['visibility' => 'hidden']);

        $names = collect($this->getJson('/search/suggest?q=terea')->json('results'))->pluck('name');

        $this->assertSame(['Visible Terea'], $names->all());
    }

    public function test_logs_only_when_log_flag_set_and_dedupes(): void
    {
        $this->product('TEREA Amber');

        // Live keystroke fetches (no log flag) record nothing.
        $this->getJson('/search/suggest?q=terea');
        $this->getJson('/search/suggest?q=terea');
        $this->assertSame(0, SearchLog::count());

        // Settled query logs once; a rapid repeat is deduped by the guard.
        $this->getJson('/search/suggest?q=terea&log=1');
        $this->getJson('/search/suggest?q=terea&log=1');
        $this->assertSame(1, SearchLog::count());

        $log = SearchLog::first();
        $this->assertSame('terea', $log->query);   // normalized lowercase
        $this->assertSame(1, $log->results_count);
    }

    public function test_zero_result_search_is_logged_with_count_zero(): void
    {
        $this->getJson('/search/suggest?q=nonexistentflavor&log=1');

        $this->assertSame(1, SearchLog::where('results_count', 0)->count());
    }

    public function test_analytics_aggregates_top_and_zero_terms(): void
    {
        $this->product('TEREA Amber');
        SearchLog::insert([
            ['query' => 'amber', 'results_count' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['query' => 'amber', 'results_count' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['query' => 'unicorn', 'results_count' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $stats = ProductSearch::analytics(30);

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['no_results']);
        $this->assertSame('amber', $stats['top']->first()->query);
        $this->assertSame(2, (int) $stats['top']->first()->hits);
        $this->assertSame('unicorn', $stats['zero']->first()->query);
    }

    public function test_full_results_page_shares_the_ranked_query(): void
    {
        $this->product('TEREA Amber Carton');
        $this->product('Unrelated Item');

        $this->get('/search?q=terea')->assertOk()->assertSee('TEREA Amber Carton')->assertDontSee('Unrelated Item');
        $this->assertSame(1, SearchLog::where('query', 'terea')->count());
    }
}
