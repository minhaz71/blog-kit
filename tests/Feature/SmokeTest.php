<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_200(): void
    {
        $this->get('/')->assertStatus(200);
    }

    public function test_robots_txt_is_served(): void
    {
        $this->get('/robots.txt')
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/plain; charset=UTF-8');
    }

    public function test_robots_txt_welcomes_ai_search_but_blocks_training(): void
    {
        $res = $this->get('/robots.txt')->assertOk();

        // Search & citation engines welcomed.
        $res->assertSee("User-agent: OAI-SearchBot", false)
            ->assertSee("User-agent: PerplexityBot", false)
            ->assertSee('Content-Signal: search=yes, ai-input=yes, ai-train=no', false);

        // Model-training crawlers blocked.
        $res->assertSee("User-agent: GPTBot\nDisallow: /", false)
            ->assertSee("User-agent: CCBot\nDisallow: /", false)
            ->assertSee("User-agent: Google-Extended\nDisallow: /", false);

        // Private areas blocked, sitemap advertised.
        $res->assertSee('Disallow: /checkout', false)
            ->assertSee('Sitemap:', false);
    }

    public function test_sitemap_index_is_served(): void
    {
        $this->get('/sitemap.xml')->assertStatus(200);
    }
}
