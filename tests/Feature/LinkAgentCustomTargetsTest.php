<?php

namespace Tests\Feature;

use App\Models\CustomLinkTarget;
use App\Models\InternalLink;
use App\Models\Product;
use App\Services\Seo\InternalLinkScanner;
use App\Services\Seo\LinkApplier;
use App\Services\Seo\LinkDictionary;
use App\Services\Seo\LinkSuggestionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkAgentCustomTargetsTest extends TestCase
{
    use RefreshDatabase;

    // ── Feature 1: unlink ────────────────────────────────────────────
    public function test_unlink_removes_a_specific_link_but_keeps_anchor_text(): void
    {
        $source = Product::create([
            'name' => 'Source', 'slug' => 'source', 'type' => 'simple', 'price' => 10, 'status' => 'published',
            'description' => '<p>Pairs with the <a href="/product/target">Target Kit</a> nicely.</p>',
        ]);
        $target = Product::create(['name' => 'Target Kit', 'slug' => 'target', 'type' => 'simple', 'price' => 12, 'status' => 'published']);

        $ok = app(LinkApplier::class)->unlink($source, $target->url(), 'Target Kit');

        $this->assertTrue($ok);
        $html = $source->fresh()->description;
        $this->assertStringNotContainsString('<a ', $html);      // link gone
        $this->assertStringContainsString('Target Kit', $html);  // words kept
    }

    // ── Feature 2: custom targets enter the dictionary ───────────────
    public function test_custom_target_becomes_a_linkable_target(): void
    {
        CustomLinkTarget::create([
            'label' => 'Homepage', 'url' => '/', 'anchor_phrases' => ['TEREA Dubai', 'TEREA UAE'],
            'weight' => 70, 'max_links' => 15, 'is_active' => true,
        ]);

        $stats = app(LinkDictionary::class)->rebuild();

        // Phrases for the homepage target now exist in the dictionary.
        $this->assertGreaterThan(0, \App\Models\LinkTarget::where('target_type', CustomLinkTarget::class)->count());
    }

    public function test_scanner_indexes_links_to_custom_target_urls(): void
    {
        $home = CustomLinkTarget::create([
            'label' => 'Homepage', 'url' => '/', 'anchor_phrases' => ['TEREA Dubai'],
            'weight' => 70, 'max_links' => 15, 'is_active' => true,
        ]);

        Product::create([
            'name' => 'Linker', 'slug' => 'linker', 'type' => 'simple', 'price' => 10, 'status' => 'published',
            'description' => '<p>Best <a href="/">TEREA Dubai</a> shop.</p>',
        ]);

        app(InternalLinkScanner::class)->scanAll();

        $this->assertSame(1, InternalLink::where('target_type', CustomLinkTarget::class)->where('target_id', $home->id)->count());
    }

    public function test_custom_target_sharing_a_category_url_does_not_steal_the_category_inbound(): void
    {
        // Regression: a custom target pointing at a real category URL must NOT
        // reassign that category's inbound links to itself (which zeroed the
        // category's incoming count in the report).
        $category = \App\Models\Category::create([
            'name' => 'TEREA UAE', 'slug' => 'terea-uae', 'is_active' => true,
        ]);
        $catUrl = $category->url();

        Product::create([
            'name' => 'Linker', 'slug' => 'linker', 'type' => 'simple', 'price' => 10, 'status' => 'published',
            'description' => '<p>See the <a href="'.$catUrl.'">TEREA UAE range</a>.</p>',
        ]);

        // Someone (mis)configures a custom target with the same URL as the category.
        $custom = CustomLinkTarget::create([
            'label' => 'TEREA UAE promo', 'url' => parse_url($catUrl, PHP_URL_PATH),
            'anchor_phrases' => ['TEREA UAE'], 'weight' => 90, 'max_links' => 15, 'is_active' => true,
        ]);

        app(InternalLinkScanner::class)->scanAll();

        // The link counts for the CATEGORY, not the custom target.
        $this->assertSame(1, InternalLink::where('target_type', \App\Models\Category::class)->where('target_id', $category->id)->count());
        $this->assertSame(0, InternalLink::where('target_type', CustomLinkTarget::class)->where('target_id', $custom->id)->count());
    }

    public function test_saving_a_custom_target_auto_regenerates_suggestions(): void
    {
        // A live product mentions the phrase in plain text (no link yet).
        $product = Product::create([
            'name' => 'Wants Home Link', 'slug' => 'wants-home-link', 'type' => 'simple', 'price' => 10,
            'status' => 'published', 'description' => '<p>Looking for TEREA Dubai stock today.</p>',
        ]);

        $this->assertSame(0, \App\Models\LinkSuggestion::where('target_type', CustomLinkTarget::class)->count());

        // Adding the target must, on its own, make the link agent propose it —
        // no manual "Rebuild" needed. (In tests BackgroundProcess is a no-op,
        // so the observer's inline fallback rebuilds + scans synchronously.)
        CustomLinkTarget::create([
            'label' => 'Homepage', 'url' => '/', 'anchor_phrases' => ['TEREA Dubai'],
            'weight' => 80, 'max_links' => 15, 'is_active' => true,
        ]);

        $this->assertGreaterThan(
            0,
            \App\Models\LinkSuggestion::where('source_id', $product->id)
                ->where('target_type', CustomLinkTarget::class)
                ->where('status', 'pending')
                ->count(),
            'Saving a custom target should auto-generate a suggestion for content that mentions its anchor.'
        );
    }

    public function test_deleting_a_custom_target_clears_its_links_and_suggestions(): void
    {
        $home = CustomLinkTarget::create([
            'label' => 'Homepage', 'url' => '/', 'anchor_phrases' => ['TEREA Dubai'],
            'weight' => 80, 'max_links' => 15, 'is_active' => true,
        ]);
        $p = Product::create([
            'name' => 'Linker', 'slug' => 'linker', 'type' => 'simple', 'price' => 10, 'status' => 'published',
            'description' => '<p>The <a href="/">TEREA Dubai</a> range.</p>',
        ]);
        InternalLink::create(['source_type' => Product::class, 'source_id' => $p->id,
            'target_type' => CustomLinkTarget::class, 'target_id' => $home->id, 'anchor' => 'TEREA Dubai']);

        $home->delete();

        $this->assertSame(0, InternalLink::where('target_type', CustomLinkTarget::class)->where('target_id', $home->id)->count());
        $this->assertSame(0, \App\Models\LinkSuggestion::where('target_type', CustomLinkTarget::class)->where('target_id', $home->id)->count());
    }

    public function test_unmatched_anchor_phrases_flags_anchors_absent_from_content(): void
    {
        Product::create([
            'name' => 'In Stock', 'slug' => 'in-stock', 'type' => 'simple', 'price' => 10, 'status' => 'published',
            'description' => '<p>Buy genuine TEREA Dubai stock here.</p>',
        ]);

        $target = CustomLinkTarget::create([
            'label' => 'Homepage', 'url' => '/',
            'anchor_phrases' => ['TEREA Dubai', 'never written phrase xyz'],
            'weight' => 70, 'max_links' => 15, 'is_active' => true,
        ]);

        // "TEREA Dubai" appears in a product; the other phrase appears nowhere.
        $this->assertSame(['never written phrase xyz'], $target->fresh()->unmatchedAnchorPhrases());
    }

    public function test_phrase_absent_when_only_a_draft_product_mentions_it(): void
    {
        // Draft content is not scanned, so a phrase only there never matches.
        Product::create([
            'name' => 'Draft', 'slug' => 'draft', 'type' => 'simple', 'price' => 10, 'status' => 'draft',
            'description' => '<p>Secret hidden phrase abc.</p>',
        ]);

        $this->assertFalse(CustomLinkTarget::phraseAppearsInContent('Secret hidden phrase abc'));
    }

    public function test_admin_screen_shows_the_never_match_warning(): void
    {
        CustomLinkTarget::create([
            'label' => 'Homepage', 'url' => '/',
            'anchor_phrases' => ['phrase found nowhere at all'],
            'weight' => 70, 'max_links' => 15, 'is_active' => true,
        ]);

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $admin = \App\Models\User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\CustomLinkTargetResource\Pages\ManageCustomLinkTargets::class)
            ->assertOk()
            ->assertSee('never match');
    }

    // ── Feature 3: site-wide cap ─────────────────────────────────────
    public function test_custom_target_respects_site_wide_max_links_cap(): void
    {
        $home = CustomLinkTarget::create([
            'label' => 'Homepage', 'url' => '/', 'anchor_phrases' => ['TEREA Dubai'],
            'weight' => 90, 'max_links' => 1, 'is_active' => true,
        ]);

        // One live link already exists → cap (1) is reached.
        $p = Product::create([
            'name' => 'Has Link', 'slug' => 'has-link', 'type' => 'simple', 'price' => 10, 'status' => 'published',
            'description' => '<p>The <a href="/">TEREA Dubai</a> range.</p>',
        ]);
        InternalLink::create(['source_type' => Product::class, 'source_id' => $p->id,
            'target_type' => CustomLinkTarget::class, 'target_id' => $home->id, 'anchor' => 'TEREA Dubai']);

        // Another product mentions the phrase but the cap is full → no suggestion.
        $other = Product::create([
            'name' => 'Wants Link', 'slug' => 'wants-link', 'type' => 'simple', 'price' => 10, 'status' => 'published',
            'description' => '<p>Looking for TEREA Dubai stock today.</p>',
        ]);

        app(LinkDictionary::class)->rebuild();
        app(LinkSuggestionEngine::class)->scanSource($other->fresh());

        $this->assertSame(0, \App\Models\LinkSuggestion::where('source_id', $other->id)
            ->where('target_type', CustomLinkTarget::class)->count());
    }
}
