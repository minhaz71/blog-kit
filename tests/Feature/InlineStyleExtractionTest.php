<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InlineStyleExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_style_blocks_move_from_content_to_custom_css_on_save(): void
    {
        $page = Page::create([
            'title' => 'Styled',
            'slug' => 'styled',
            'status' => 'published',
            'content' => '<style>.hero { color: red; }</style><p class="hero">Hello</p>',
        ]);

        $this->assertSame('<p class="hero">Hello</p>', $page->content);
        $this->assertStringContainsString('.hero { color: red; }', $page->custom_css);

        // Served as this page's own stylesheet.
        $this->get("/custom-css/page/{$page->id}.css")
            ->assertStatus(200)
            ->assertSee('.hero { color: red; }', false);
    }

    public function test_saving_again_does_not_duplicate_the_css(): void
    {
        $page = Page::create([
            'title' => 'Once', 'slug' => 'once', 'status' => 'published',
            'content' => '<style>.a { margin: 0; }</style><p>x</p>',
        ]);

        // Re-save with the same style block pasted again.
        $page->update(['content' => '<style>.a { margin: 0; }</style><p>x edited</p>']);

        $this->assertSame(1, substr_count($page->custom_css, '.a { margin: 0; }'));
        $this->assertSame('<p>x edited</p>', $page->content);
    }

    public function test_multiple_style_blocks_and_existing_custom_css_append(): void
    {
        $post = Post::create([
            'title' => 'Multi', 'slug' => 'multi', 'status' => 'draft',
            'author_id' => User::factory()->create()->id,
            'custom_css' => '.existing { padding: 4px; }',
            'content' => '<style>.one { color: blue; }</style><p>Body</p><style>.two { color: green; }</style>',
        ]);

        $this->assertSame('<p>Body</p>', $post->content);
        $this->assertStringContainsString('.existing { padding: 4px; }', $post->custom_css);
        $this->assertStringContainsString('.one { color: blue; }', $post->custom_css);
        $this->assertStringContainsString('.two { color: green; }', $post->custom_css);
    }

    public function test_product_descriptions_also_extract_styles(): void
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget', 'type' => 'simple',
            'price' => 10, 'status' => 'published',
            'description' => '<style>.spec { font-weight: bold; }</style><p>Specs</p>',
        ]);

        $this->assertSame('<p>Specs</p>', $product->description);
        $this->assertStringContainsString('.spec { font-weight: bold; }', $product->custom_css);
    }

    public function test_content_without_style_tags_is_untouched(): void
    {
        $page = Page::create([
            'title' => 'Plain', 'slug' => 'plain-2', 'status' => 'published',
            'content' => '<p style="color: red;">Inline attribute stays</p>',
        ]);

        $this->assertSame('<p style="color: red;">Inline attribute stays</p>', $page->content);
        $this->assertNull($page->custom_css);
    }
}
