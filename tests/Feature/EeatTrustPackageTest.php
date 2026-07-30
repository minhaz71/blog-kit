<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EeatTrustPackageTest extends TestCase
{
    use RefreshDatabase;

    protected function author(): User
    {
        return User::factory()->create([
            'name' => 'Rashid Al Marri',
            'job_title' => 'Heated-tobacco specialist',
            'bio' => 'Tests every TEREA flavor sold in the UAE.',
            'social_links' => ['https://linkedin.com/in/rashid'],
        ]);
    }

    protected function makePost(User $author): Post
    {
        $category = PostCategory::create(['name' => 'Guides', 'slug' => 'guides']);

        return Post::create([
            'title' => 'TEREA Flavor Guide', 'slug' => 'terea-flavor-guide',
            'status' => 'published', 'published_at' => now(),
            'author_id' => $author->id, 'post_category_id' => $category->id,
            'content' => '<p>Guide body.</p>',
        ]);
    }

    public function test_blog_posts_carry_author_byline_box_and_person_schema(): void
    {
        $post = $this->makePost($this->author());

        $response = $this->get($post->url())->assertOk();

        // Visible E-E-A-T byline box.
        $response->assertSee('Rashid Al Marri');
        $response->assertSee('Heated-tobacco specialist');
        $response->assertSee('Tests every TEREA flavor sold in the UAE.');

        // Person schema with credentials, tied to the Organization.
        $response->assertSee('"jobTitle":"Heated-tobacco specialist"', false);
        $response->assertSee('"sameAs":["https://linkedin.com/in/rashid"]', false);
        $response->assertSee('"worksFor"', false);
    }

    public function test_author_archive_is_a_profile_page(): void
    {
        $author = $this->author();
        $this->makePost($author);

        $this->get(route('blog.author', $author->public_slug))
            ->assertOk()
            ->assertSee('"@type":"ProfilePage"', false)
            ->assertSee('Rashid Al Marri');
    }

    public function test_about_page_emits_about_page_schema_linked_to_organization(): void
    {
        $page = Page::create(['title' => 'About Us', 'slug' => 'about-us', 'content' => '<p>Who we are.</p>', 'status' => 'published']);
        $page->seoMeta()->create(['schema_type' => 'AboutPage']);

        $this->get('/about-us')
            ->assertOk()
            ->assertSee('"@type":"AboutPage"', false)
            ->assertSee('"mainEntity":{"@id":"'.url('/').'#organization"}', false);
    }

    public function test_llms_txt_is_dynamic_and_auto_updates_on_content_change(): void
    {
        Setting::set('general.site_name', 'Terea Hub');
        Setting::set('seo.local_business_area_served', 'Dubai, Sharjah, Ajman');

        Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 30, 'status' => 'published',
            'short_description' => '<p>Rich roasted tobacco sticks.</p>',
        ]);

        $first = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringContainsString('# Terea Hub', $first);
        $this->assertStringContainsString('Delivery area: Dubai, Sharjah, Ajman', $first);
        // llms.txt links point at the markdown (.md) variant of each page.
        $this->assertStringContainsString('[TEREA Amber]('.url('/product/terea-amber').'.md)', $first);
        $this->assertStringContainsString('adult users only', $first);

        // New product published → llms.txt regenerates without any cron.
        Product::create([
            'name' => 'TEREA Sienna', 'slug' => 'terea-sienna', 'type' => 'simple',
            'price' => 30, 'status' => 'published',
        ]);

        $this->assertStringContainsString('TEREA Sienna', $this->get('/llms.txt')->getContent());

        // Served from .well-known too.
        $this->get('/.well-known/llms.txt')->assertOk()->assertSee('# Terea Hub');
    }

    public function test_security_txt_serves_rfc9116_contact(): void
    {
        Setting::set('general.contact_email', 'security@tereahub.ae');

        $this->get('/.well-known/security.txt')
            ->assertOk()
            ->assertSee('Contact: mailto:security@tereahub.ae')
            ->assertSee('Expires:');
    }

    public function test_robots_txt_blocks_private_areas_and_welcomes_ai_crawlers(): void
    {
        $robots = $this->get('/robots.txt')->assertOk()->getContent();

        foreach (['/admin', '/hmmail/', '/livewire/', '/cart', '/checkout', '/my-account', '/login', '/*?sort='] as $blocked) {
            $this->assertStringContainsString('Disallow: '.$blocked, $robots);
        }

        // Product images stay crawlable; AI bots are explicitly welcomed.
        $this->assertStringContainsString('Allow: /storage/products/', $robots);
        $this->assertStringContainsString('User-agent: GPTBot', $robots);
        $this->assertStringContainsString('User-agent: PerplexityBot', $robots);
        $this->assertStringContainsString('llms.txt', $robots);
        $this->assertStringContainsString('Sitemap: '.route('sitemap.index'), $robots);
    }
}
