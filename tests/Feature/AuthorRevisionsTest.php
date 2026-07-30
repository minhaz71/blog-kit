<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Support\TextDiff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorRevisionsTest extends TestCase
{
    use RefreshDatabase;

    protected function makeAuthorWithPost(): array
    {
        $author = User::factory()->create([
            'name' => 'admin-login-name',
            'display_name' => 'Terea Hub Editorial',
            'bio' => 'Writes flavor guides.',
            'is_active' => true,
        ]);

        $post = Post::create([
            'author_id' => $author->id,
            'title' => 'Original title',
            'slug' => 'revision-post',
            'excerpt' => 'Original excerpt',
            'content' => '<p>The quick brown fox jumps over the lazy dog.</p>',
            'status' => 'published',
            'published_at' => now()->subHour(),
        ]);

        return [$author, $post];
    }

    // ── Public author identity ────────────────────────────────────────

    public function test_author_url_is_random_and_login_identity_never_leaks(): void
    {
        [$author, $post] = $this->makeAuthorWithPost();

        // Random slug: auto-generated, not derived from name or email.
        $this->assertNotNull($author->public_slug);
        $this->assertStringNotContainsString('admin', $author->public_slug);
        $this->assertSame(12, strlen($author->public_slug));

        // Author page opens by the random slug only.
        $this->get('/blog/author/'.$author->public_slug)->assertOk()
            ->assertSee('Terea Hub Editorial');
        $this->get('/blog/author/'.$author->id)->assertNotFound();
        $this->get('/blog/author/admin-login-name')->assertNotFound();

        // The blog post byline shows the public name, never the login name.
        $html = $this->get('/blog/revision-post')->assertOk()->getContent();
        $this->assertStringContainsString('Terea Hub Editorial', $html);
        $this->assertStringNotContainsString('admin-login-name', $html);
        $this->assertStringContainsString('/blog/author/'.$author->public_slug, $html);
    }

    // ── Revision history ──────────────────────────────────────────────

    public function test_edits_snapshot_the_previous_version_with_the_editor(): void
    {
        [$author, $post] = $this->makeAuthorWithPost();
        $editor = User::factory()->create(['is_active' => true]);

        $this->actingAs($editor);
        $post->update(['content' => '<p>The quick brown fox now sprints past the sleeping dog.</p>']);

        $revision = $post->revisions()->first();
        $this->assertNotNull($revision);
        $this->assertSame('<p>The quick brown fox jumps over the lazy dog.</p>', $revision->content);
        $this->assertSame($editor->id, $revision->user_id);
        $this->assertSame($editor->id, $post->fresh()->last_edited_by);

        // Non-content changes (status flip) create no snapshot.
        $post->update(['status' => 'draft']);
        $this->assertSame(1, $post->revisions()->count());
    }

    public function test_ai_edits_are_recorded_as_system(): void
    {
        [, $post] = $this->makeAuthorWithPost();

        auth()->logout();
        $post->update(['title' => 'AI rewrote this title']);

        $revision = $post->revisions()->first();
        $this->assertNull($revision->user_id);
        $this->assertSame('AI writer / system', $revision->editorLabel());
        $this->assertNull($post->fresh()->last_edited_by);
    }

    public function test_restore_rolls_back_and_keeps_the_replaced_version(): void
    {
        [, $post] = $this->makeAuthorWithPost();

        $post->update(['content' => '<p>Version two.</p>']);
        $first = $post->revisions()->first(); // holds version one

        $post->restoreRevision($first);

        $post->refresh();
        $this->assertSame('<p>The quick brown fox jumps over the lazy dog.</p>', $post->content);
        // Version two was snapshotted before the rollback — nothing lost.
        $this->assertSame(2, $post->revisions()->count());
        $this->assertSame('<p>Version two.</p>', $post->revisions()->first()->content);
    }

    public function test_revision_trail_is_capped(): void
    {
        [, $post] = $this->makeAuthorWithPost();

        for ($i = 1; $i <= Post::MAX_REVISIONS + 5; $i++) {
            $post->update(['content' => "<p>Version {$i}</p>"]);
        }

        $this->assertSame(Post::MAX_REVISIONS, $post->revisions()->count());
    }

    // ── Diff + admin page ─────────────────────────────────────────────

    public function test_text_diff_marks_insertions_and_deletions(): void
    {
        $html = TextDiff::html(
            '<p>The quick brown fox jumps over the lazy dog.</p>',
            '<p>The quick red fox leaps over the lazy dog.</p>',
        );

        $this->assertStringContainsString('<del>brown</del>', $html);
        $this->assertStringContainsString('<ins>red</ins>', $html);
        $this->assertStringContainsString('<del>jumps</del>', $html);
        $this->assertStringContainsString('<ins>leaps</ins>', $html);
        $this->assertStringContainsString('lazy dog.', $html); // unchanged text plain

        $this->assertFalse(TextDiff::changed('<p>Same</p>', '<div>Same</div>')); // markup-only change
        $this->assertTrue(TextDiff::changed('<p>Same</p>', '<p>Different</p>'));
    }

    public function test_admin_revisions_page_renders_with_history(): void
    {
        $this->seed();
        [, $post] = $this->makeAuthorWithPost();
        $post->update(['content' => '<p>Edited once.</p>']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('Super Admin');

        $this->actingAs($admin)
            ->get("/admin/posts/{$post->id}/revisions")
            ->assertOk()
            ->assertSee('Compare from')
            ->assertSee('Current live version');
    }
}
