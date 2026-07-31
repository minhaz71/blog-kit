<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use App\Services\Network\NetworkPostIngestor;
use App\Services\Network\NetworkPostPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NetworkMediaAuthorTest extends TestCase
{
    use RefreshDatabase;

    /** A real 1x1 PNG. */
    protected function pngBase64(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    /** @param array<string,mixed> $overrides */
    protected function payload(array $overrides = []): array
    {
        return array_replace([
            'network_post_id' => 7,
            'title' => 'Networked Post',
            'slug' => 'networked-post',
            'excerpt' => 'Dek.',
            'content' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now()->toIso8601String(),
            'has_featured_image' => false,
            'featured_image' => null,
            'faqs' => [],
        ], $overrides);
    }

    public function test_ingest_saves_a_shipped_featured_image(): void
    {
        User::create(['name' => 'Fallback', 'email' => 'fb@site.example', 'password' => bcrypt('x'), 'is_active' => true]);
        Storage::fake('public');
        $bytes = base64_decode($this->pngBase64());

        $data = $this->payload([
            'has_featured_image' => true,
            'featured_image' => ['filename' => 'hero.png', 'mime' => 'image/png', 'sha256' => hash('sha256', $bytes), 'data' => $this->pngBase64()],
            'featured_image_alt' => 'A hero',
        ]);

        $post = (new NetworkPostIngestor)->apply('hubkey', $data);

        $this->assertNotNull($post->featured_image);
        $this->assertTrue(Storage::disk('public')->exists($post->featured_image));
        $this->assertSame('A hero', $post->featured_image_alt);
    }

    public function test_ingest_rejects_non_image_bytes(): void
    {
        User::create(['name' => 'Fallback', 'email' => 'fb2@site.example', 'password' => bcrypt('x'), 'is_active' => true]);
        Storage::fake('public');

        $data = $this->payload([
            'has_featured_image' => true,
            'featured_image' => ['filename' => 'evil.png', 'mime' => 'image/png', 'sha256' => 'x', 'data' => base64_encode('<?php echo 1; ?> not an image')],
        ]);

        $post = (new NetworkPostIngestor)->apply('hubkey', $data);

        $this->assertNull($post->featured_image); // arbitrary bytes are never written
    }

    public function test_ingest_creates_an_attribution_only_author(): void
    {
        $data = $this->payload([
            'author' => ['name' => 'Jane Expert', 'email' => 'jane@writers.example', 'job_title' => 'Editor', 'bio' => '15 years in the field.', 'social_links' => ['https://x.com/jane']],
        ]);

        $post = (new NetworkPostIngestor)->apply('hubkey', $data);
        $author = $post->author;

        $this->assertSame('Jane Expert', $author->name);
        $this->assertSame('Editor', $author->job_title);
        $this->assertSame('15 years in the field.', $author->bio);
        $this->assertFalse((bool) $author->is_active); // cannot log in
    }

    public function test_ingest_maps_existing_author_without_overwriting(): void
    {
        $existing = User::create(['name' => 'Local Sam', 'email' => 'sam@site.example', 'password' => bcrypt('x'), 'is_active' => true, 'bio' => 'Original bio']);

        $data = $this->payload([
            'author' => ['name' => 'Hub Sam', 'email' => 'sam@site.example', 'bio' => 'Different bio from hub'],
        ]);

        $post = (new NetworkPostIngestor)->apply('hubkey', $data);

        $this->assertSame($existing->id, $post->author_id);
        $this->assertSame('Original bio', $existing->fresh()->bio); // not overwritten
        $this->assertSame(1, User::where('email', 'sam@site.example')->count());
    }

    public function test_content_hash_ignores_slug_and_image_filename(): void
    {
        $base = $this->payload(['featured_image' => ['filename' => 'a.png', 'mime' => 'image/png', 'sha256' => 'ABC', 'data' => 'AAAA'], 'has_featured_image' => true]);
        $renamed = $this->payload(['slug' => 'a-totally-different-slug', 'featured_image' => ['filename' => 'renamed-on-spoke.png', 'mime' => 'image/png', 'sha256' => 'ABC', 'data' => 'ZZZZ'], 'has_featured_image' => true]);

        // Same content + same image fingerprint (sha) → same hash, despite
        // different slug, filename and raw base64 bytes.
        $this->assertSame(NetworkPostPayload::hash($base), NetworkPostPayload::hash($renamed));

        // Different image fingerprint → different hash.
        $changed = $this->payload(['featured_image' => ['filename' => 'a.png', 'mime' => 'image/png', 'sha256' => 'DIFFERENT', 'data' => 'AAAA'], 'has_featured_image' => true]);
        $this->assertNotSame(NetworkPostPayload::hash($base), NetworkPostPayload::hash($changed));
    }
}
