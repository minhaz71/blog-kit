<?php

namespace Tests\Feature;

use App\Models\BrokenLink;
use App\Models\Product;
use App\Services\Seo\InternalLinkScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When a product/post is deleted, every page still linking to it is recorded
 * as a broken link so the admin can fix or repoint it — and the report clears
 * when the target is restored or the link is removed.
 */
class BrokenLinkReportTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $slug, string $description = ''): Product
    {
        return Product::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'type' => 'simple',
            'price' => 10, 'status' => 'published', 'description' => $description,
        ]);
    }

    public function test_deleting_a_linked_product_reports_broken_links_on_the_linking_pages(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna',
            '<p>Bolder than <a href="/product/terea-amber">TEREA Amber</a>.</p>');

        app(InternalLinkScanner::class)->scanAll();

        // Sienna links to Amber; deleting Amber breaks that link.
        $amber->delete();

        $report = BrokenLink::open()->first();
        $this->assertNotNull($report, 'Expected a broken-link report');
        $this->assertSame(Product::class, $report->source_type);
        $this->assertSame($sienna->id, $report->source_id);
        $this->assertSame($amber->url(), $report->url);
        $this->assertSame('TEREA Amber', $report->anchor);
    }

    public function test_restoring_the_target_resolves_the_broken_link(): void
    {
        $amber = $this->product('terea-amber');
        $this->product('terea-sienna', '<p>See <a href="/product/terea-amber">Amber</a>.</p>');
        app(InternalLinkScanner::class)->scanAll();

        $amber->delete();
        $this->assertSame(1, BrokenLink::open()->count());

        $amber->restore();
        $this->assertSame(0, BrokenLink::open()->count());
    }

    public function test_editing_the_source_to_remove_the_link_resolves_the_report(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna', '<p>See <a href="/product/terea-amber">Amber</a>.</p>');
        app(InternalLinkScanner::class)->scanAll();

        $amber->delete();
        $this->assertSame(1, BrokenLink::open()->count());

        // Admin edits Sienna and removes the dead link → report auto-resolves.
        $sienna->update(['description' => '<p>No links here anymore.</p>']);

        $this->assertSame(0, BrokenLink::open()->count());
    }

    public function test_unlink_removes_the_dead_anchor_but_keeps_its_text(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna',
            '<p>Bolder than <a href="/product/terea-amber">TEREA Amber</a>, and that is fine.</p>');
        app(InternalLinkScanner::class)->scanAll();

        $amber->delete();
        $report = BrokenLink::open()->firstOrFail();

        $removed = $report->unlink();

        $this->assertSame(1, $removed);
        $sienna->refresh();
        // Anchor gone, text kept, sentence intact.
        $this->assertStringNotContainsString('<a', $sienna->description);
        $this->assertStringContainsString('Bolder than TEREA Amber, and that is fine.', strip_tags($sienna->description));
        // Report resolved.
        $this->assertNotNull($report->fresh()->resolved_at);
    }

    public function test_unlink_matches_absolute_urls_and_spares_other_links(): void
    {
        $amber = $this->product('terea-amber');
        $yellow = $this->product('terea-yellow');
        $sienna = $this->product('terea-sienna',
            '<p>See <a href="'.url('/product/terea-amber').'">Amber</a> or <a href="/product/terea-yellow">Yellow</a>.</p>');
        app(InternalLinkScanner::class)->scanAll();

        $amber->delete();

        BrokenLink::open()->firstOrFail()->unlink();

        $sienna->refresh();
        // The absolute-URL dead link is unwrapped; the healthy link survives.
        $this->assertStringNotContainsString('terea-amber', $sienna->description);
        $this->assertStringContainsString('href="/product/terea-yellow"', $sienna->description);
        $this->assertStringContainsString('See Amber or', strip_tags($sienna->description));
    }

    public function test_unlink_with_missing_source_still_resolves_the_report(): void
    {
        $amber = $this->product('terea-amber');
        $sienna = $this->product('terea-sienna', '<p><a href="/product/terea-amber">Amber</a></p>');
        app(InternalLinkScanner::class)->scanAll();
        $amber->delete();

        // The linking page itself is force-deleted before anyone fixes it.
        $sienna->forceDelete();

        $report = BrokenLink::open()->firstOrFail();
        $this->assertSame(0, $report->unlink());
        $this->assertNotNull($report->fresh()->resolved_at);
    }

    public function test_no_report_when_nothing_links_to_the_deleted_product(): void
    {
        $lonely = $this->product('terea-yellow');
        app(InternalLinkScanner::class)->scanAll();

        $lonely->delete();

        $this->assertSame(0, BrokenLink::count());
    }
}
