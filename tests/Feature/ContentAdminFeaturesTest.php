<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentAdminFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');

        return $user;
    }

    public function test_custom_css_is_served_as_a_stylesheet_file(): void
    {
        $page = Page::create([
            'title' => 'Styled page',
            'slug' => 'styled-page',
            'content' => '<p>Hello</p>',
            'status' => 'published',
            'custom_css' => '.hero { color: red; }',
        ]);

        $this->get("/custom-css/page/{$page->id}.css")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'text/css; charset=utf-8')
            ->assertSee('.hero { color: red; }', false);

        // The page itself links the stylesheet instead of inlining it.
        $this->get('/styled-page')
            ->assertStatus(200)
            ->assertSee("custom-css/page/{$page->id}.css", false);
    }

    public function test_entity_css_404s_when_empty(): void
    {
        $page = Page::create([
            'title' => 'Plain', 'slug' => 'plain', 'content' => '<p>x</p>', 'status' => 'published',
        ]);

        $this->get("/custom-css/page/{$page->id}.css")->assertStatus(404);
        $this->get('/custom-css/page/999999.css')->assertStatus(404);
    }

    public function test_draft_post_is_hidden_from_guests_but_previewable_by_admins(): void
    {
        $admin = $this->admin();

        $post = Post::create([
            'title' => 'Draft post',
            'slug' => 'draft-post',
            'content' => '<p>Secret</p>',
            'status' => 'draft',
            'author_id' => $admin->id,
        ]);

        $this->get('/blog/draft-post')->assertStatus(404);

        $this->actingAs($admin)
            ->get('/blog/draft-post')
            ->assertStatus(200)
            ->assertSee('Draft preview');
    }

    public function test_draft_page_is_hidden_from_guests_but_previewable_by_admins(): void
    {
        $admin = $this->admin();

        Page::create([
            'title' => 'Draft page', 'slug' => 'draft-page', 'content' => '<p>x</p>', 'status' => 'draft',
        ]);

        $this->get('/draft-page')->assertStatus(404);
        $this->actingAs($admin)->get('/draft-page')->assertStatus(200)->assertSee('Draft preview');
    }

    public function test_posts_and_pages_soft_delete(): void
    {
        $admin = $this->admin();

        $post = Post::create([
            'title' => 'Trash me', 'slug' => 'trash-me', 'content' => '<p>x</p>',
            'status' => 'published', 'published_at' => now()->subDay(), 'author_id' => $admin->id,
        ]);

        $post->delete();

        $this->assertSoftDeleted('posts', ['id' => $post->id]);
        $this->get('/blog/trash-me')->assertStatus(404);

        $post->restore();
        $this->get('/blog/trash-me')->assertStatus(200);
    }

    public function test_inline_styles_survive_in_rendered_content(): void
    {
        Page::create([
            'title' => 'Inline', 'slug' => 'inline-css',
            'content' => '<p style="color: rgb(255, 0, 0);">Red text</p>',
            'status' => 'published',
        ]);

        $this->get('/inline-css')
            ->assertStatus(200)
            ->assertSee('style="color: rgb(255, 0, 0);"', false);
    }
}
