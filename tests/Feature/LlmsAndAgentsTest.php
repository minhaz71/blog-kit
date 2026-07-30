<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmsAndAgentsTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCatalog(): void
    {
        Product::create([
            'name' => 'TEREA Amber', 'slug' => 'terea-amber', 'type' => 'simple',
            'price' => 120, 'status' => 'published', 'short_description' => 'Rich tobacco.',
        ]);
    }

    public function test_llms_txt_is_markdown_with_optional_section_and_md_links(): void
    {
        $this->seedCatalog();

        $res = $this->get('/llms.txt');

        $res->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('## Optional', false)
            ->assertSee('/llms-full.txt', false)
            ->assertSee('.md)', false); // entry links point at markdown variants
    }

    public function test_llms_full_txt_returns_concatenated_markdown(): void
    {
        $this->seedCatalog();

        $this->get('/llms-full.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('full content export', false)
            ->assertSee('# TEREA Amber', false);
    }

    public function test_agents_json_is_valid_manifest(): void
    {
        $res = $this->get('/.well-known/agents.json');

        $res->assertOk();
        $this->assertStringContainsString('application/json', (string) $res->headers->get('Content-Type'));

        $data = $res->json();
        $this->assertSame('0.1', $data['version']);
        $this->assertArrayHasKey('capabilities', $data);
        $this->assertArrayHasKey('markdown', $data['capabilities']);
        $this->assertArrayHasKey('discovery', $data);
        $this->assertStringEndsWith('/llms.txt', $data['discovery']['llms_txt']);

        // Alias at the root also works.
        $this->get('/agents.json')->assertOk();
    }

    public function test_agents_json_toggle_off_is_404(): void
    {
        Setting::set('seo.agents_json', false);

        $this->get('/.well-known/agents.json')->assertNotFound();
    }

    public function test_api_catalog_is_valid_linkset(): void
    {
        $res = $this->get('/.well-known/api-catalog');

        $res->assertOk();
        $this->assertStringContainsString('application/linkset+json', (string) $res->headers->get('Content-Type'));

        $data = $res->json();
        $this->assertArrayHasKey('linkset', $data);
        $this->assertNotEmpty($data['linkset'][0]['anchor']);
        $this->assertArrayHasKey('item', $data['linkset'][0]);
    }

    public function test_homepage_advertises_link_header_for_agents(): void
    {
        $link = (string) $this->get('/')->headers->get('Link');

        $this->assertStringContainsString('rel="api-catalog"', $link);
        $this->assertStringContainsString('/.well-known/api-catalog', $link);
        $this->assertStringContainsString('rel="sitemap"', $link);
    }

    public function test_product_schema_includes_datemodified(): void
    {
        $this->seedCatalog();

        $this->get('/product/terea-amber')
            ->assertOk()
            ->assertSee('dateModified', false);
    }
}
