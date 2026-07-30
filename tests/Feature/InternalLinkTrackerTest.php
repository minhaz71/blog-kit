<?php

namespace Tests\Feature;

use App\Models\InternalLink;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Services\Seo\InternalLinkScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Internal link tracker: counts editorial links between products and posts
 * (relative or own-domain URLs), skips self-links and external links, and
 * re-indexes a single source incrementally when its content is edited.
 */
class InternalLinkTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $slug, string $description = ''): Product
    {
        return Product::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'description' => $description,
        ]);
    }

    public function test_scan_indexes_product_and_post_links_and_finds_orphans(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna',
            '<p>Milder than <a href="/product/terea-amber">TEREA Amber</a>. '
            .'External: <a href="https://other-shop.com/product/terea-amber">not ours</a>. '
            .'Self: <a href="/product/terea-sienna">me</a>.</p>');
        $orphan = $this->product('terea-yellow');

        $author = User::factory()->create();
        Post::create([
            'title' => 'Guide', 'slug' => 'guide', 'status' => 'published', 'author_id' => $author->id,
            'published_at' => now(),
            'content' => '<p>Try <a href="'.url('/product/terea-amber').'">Amber</a> and <a href="/blog/other">other post</a>.</p>',
        ]);
        Post::create([
            'title' => 'Other', 'slug' => 'other', 'status' => 'published', 'author_id' => $author->id,
            'published_at' => now(), 'content' => '<p>No links.</p>',
        ]);

        $stats = app(InternalLinkScanner::class)->scanAll();

        $this->assertSame(3, $stats['links']); // sienna→amber, guide→amber, guide→other

        // Amber has 2 inbound (from sienna + guide); self/external ignored.
        $this->assertSame(2, InternalLink::where('target_type', Product::class)->where('target_id', $amber->id)->count());
        $this->assertSame(0, InternalLink::where('target_type', Product::class)->where('target_id', $sienna->id)->count());
        $this->assertSame(0, InternalLink::where('target_type', Product::class)->where('target_id', $orphan->id)->count());

        // Anchor text captured.
        $this->assertSame('TEREA Amber', InternalLink::where('source_id', $sienna->id)->first()->anchor);
    }

    public function test_editing_a_product_reindexes_only_that_product(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna', '<p>plain text, no links</p>');

        app(InternalLinkScanner::class)->scanAll();
        $this->assertSame(0, InternalLink::count());

        // Observer picks up the content change and indexes the new link.
        $sienna->update(['description' => '<p>See <a href="/product/terea-amber">Amber</a>.</p>']);

        $this->assertSame(1, InternalLink::where('source_id', $sienna->id)->count());
        $this->assertSame(1, InternalLink::where('target_id', $amber->id)->where('target_type', Product::class)->count());
    }

    public function test_draft_products_do_not_count_as_link_sources(): void
    {
        $this->product('terea-amber');

        $draft = Product::create([
            'name' => 'Draft', 'slug' => 'draft-product', 'type' => 'simple', 'price' => 10,
            'status' => 'draft',
            'description' => '<p><a href="/product/terea-amber">Amber</a></p>',
        ]);

        app(InternalLinkScanner::class)->scanAll();
        $this->assertSame(0, InternalLink::count(), 'A draft page is not live — its links must not count.');

        // Publishing it makes its links real (observer re-scans on status change).
        $draft->update(['status' => 'published']);
        $this->assertSame(1, InternalLink::where('source_id', $draft->id)->count());

        // Unpublishing removes them again.
        $draft->update(['status' => 'draft']);
        $this->assertSame(0, InternalLink::where('source_id', $draft->id)->count());
    }

    public function test_deleting_a_product_removes_its_links_on_both_sides(): void
    {
        $amber = $this->product('terea-amber', '<p><a href="/product/terea-sienna">Sienna</a></p>');
        $sienna = $this->product('terea-sienna', '<p><a href="/product/terea-amber">Amber</a></p>');

        app(InternalLinkScanner::class)->scanAll();
        $this->assertSame(2, InternalLink::count());

        $sienna->delete();

        // Sienna's outbound row AND the row targeting it are both gone.
        $this->assertSame(0, InternalLink::where('source_id', $sienna->id)->count());
        $this->assertSame(0, InternalLink::where('target_id', $sienna->id)->where('target_type', Product::class)->count());
    }

    public function test_links_via_old_slugs_still_count_after_a_slug_change(): void
    {
        $amber = $this->product('terea-amber');
        $this->product('terea-sienna', '<p><a href="/product/terea-amber">Amber</a></p>');

        // Slug changes; HasSlug records the old slug for 301 redirects.
        $amber->update(['slug' => 'iqos-terea-amber-uae']);

        app(InternalLinkScanner::class)->scanAll();

        // The old-slug link 301s to the product, so it still counts.
        $this->assertSame(1, InternalLink::where('target_id', $amber->id)->where('target_type', Product::class)->count());
    }

    public function test_report_page_renders_stats_search_and_drilldown(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna', '<p>Try <a href="/product/terea-amber">TEREA Amber flavor</a>.</p>');
        $this->product('terea-yellow'); // orphan

        app(InternalLinkScanner::class)->scanAll();

        // Screens are permission-gated now — the report needs a real role.
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        // Stats + orphan badge + drill-down with anchor text.
        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Pages\InternalLinksReport::class)
            ->assertSee('Orphan pages')
            ->assertSee('Terea Yellow')
            ->call('showDetail', 'Product', $amber->id)
            ->assertSee('Linked FROM (1)')
            ->assertSee('TEREA Amber flavor') // the anchor text
            ->assertSee('Terea Sienna')       // the linking source
            ->set('search', 'sienna')
            ->assertSee('Terea Sienna')
            ->assertDontSee('Terea Yellow');
    }

    protected function category(string $slug, string $description = ''): \App\Models\Category
    {
        return \App\Models\Category::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug,
            'is_active' => true, 'description' => $description,
        ]);
    }

    public function test_scan_indexes_categories_as_both_source_and_target(): void
    {
        // Category page whose description links out to a product.
        $indo = $this->category('terea-indonesian',
            '<p>Top pick: <a href="/product/terea-amber">TEREA Amber</a>.</p>');
        // Product whose description links to the category page.
        $amber = $this->product('terea-amber',
            '<p>Part of the <a href="/category/terea-indonesian">Indonesian line</a>.</p>');

        app(InternalLinkScanner::class)->scanAll();

        // Category → product (category is a source with 1 outbound link).
        $this->assertSame(1, InternalLink::where('source_type', \App\Models\Category::class)->where('source_id', $indo->id)->count());
        // Product → category (category is a target with 1 inbound link).
        $this->assertSame(1, InternalLink::where('target_type', \App\Models\Category::class)->where('target_id', $indo->id)->count());
    }

    public function test_editing_a_category_reindexes_only_that_category(): void
    {
        $this->product('terea-amber');
        $indo = $this->category('terea-indonesian', '<p>plain text</p>');

        app(InternalLinkScanner::class)->scanAll();
        $this->assertSame(0, InternalLink::where('source_type', \App\Models\Category::class)->count());

        // Observer re-scans on a description change.
        $indo->update(['description' => '<p>See <a href="/product/terea-amber">Amber</a>.</p>']);

        $this->assertSame(1, InternalLink::where('source_type', \App\Models\Category::class)->where('source_id', $indo->id)->count());
    }

    public function test_report_page_lists_categories_with_link_counts(): void
    {
        $amber = $this->product('terea-amber');
        $this->category('terea-indonesian', '<p><a href="/product/terea-amber">Amber</a></p>');

        app(InternalLinkScanner::class)->scanAll();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Pages\InternalLinksReport::class)
            ->assertSee('Product categories')
            ->assertSee('Terea Indonesian')
            ->call('showDetail', 'Category', \App\Models\Category::where('slug', 'terea-indonesian')->value('id'))
            ->assertSee('Links TO (1)')
            ->assertSee('Terea Amber');
    }

    public function test_full_scan_sweeps_rows_for_sources_gone_stale(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna', '<p><a href="/product/terea-amber">Amber</a></p>');

        app(InternalLinkScanner::class)->scanAll();
        $this->assertSame(1, InternalLink::count());

        // Simulate drift: unpublish WITHOUT events (mass query update).
        Product::whereKey($sienna->id)->toBase()->update(['status' => 'draft']);
        $this->assertSame(1, InternalLink::count()); // observers never fired

        app(InternalLinkScanner::class)->scanAll();
        $this->assertSame(0, InternalLink::count(), 'Weekly full scan must clean up drift.');
    }
}
