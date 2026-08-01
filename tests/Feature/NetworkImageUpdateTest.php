<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Setting;
use App\Models\User;
use App\Services\Ai\ImageGenerator;
use App\Services\Ai\ThumbnailService;
use App\Services\Network\NetworkIdentity;
use App\Services\Network\NetworkSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NetworkImageUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }

    protected function configureOpenAiImages(): void
    {
        Setting::set('ai.openai_api_key', 'sk-test');
        Setting::set('ai.image_provider', 'openai');
    }

    public function test_image_generator_returns_bytes_from_openai(): void
    {
        $this->configureOpenAiImages();
        Http::fake(['*/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode($this->png())]]], 200)]);

        $this->assertTrue(ImageGenerator::isConfigured());
        $out = (new ImageGenerator)->generate('a test thumbnail');

        $this->assertNotEmpty($out['bytes']);
        $this->assertNotFalse(@getimagesizefromstring($out['bytes']));
    }

    public function test_thumbnail_gets_an_seo_friendly_filename_and_alt(): void
    {
        $this->configureOpenAiImages();
        Storage::fake('public');
        Http::fake(['*/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode($this->png())]]], 200)]);

        $author = User::create(['name' => 'A', 'email' => 'a@x.example', 'password' => bcrypt('x'), 'is_active' => true]);
        $post = Post::create(['author_id' => $author->id, 'title' => 'How to Compost at Home', 'slug' => 'how-to-compost-at-home', 'content' => '<p>x</p>', 'status' => 'published', 'published_at' => now(), 'reading_time' => 1]);

        $path = (new ThumbnailService)->generateForPost($post, 'How to Compost at Home');

        $this->assertSame('thumbnails/how-to-compost-at-home.png', $path); // descriptive, not a hash
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertSame('how-to-compost-at-home.png', basename($post->fresh()->featured_image));
        $this->assertSame('How to Compost at Home', $post->fresh()->featured_image_alt);
    }

    public function test_generator_rejects_non_image_response(): void
    {
        $this->configureOpenAiImages();
        Http::fake(['*/v1/images/generations' => Http::response(['data' => [['b64_json' => base64_encode('this is not an image')]]], 200)]);

        $this->expectException(\RuntimeException::class);
        (new ImageGenerator)->generate('x');
    }

    public function test_csv_thumbnail_column_aliases_to_generate_image(): void
    {
        $author = User::create(['name' => 'A', 'email' => 'b@x.example', 'password' => bcrypt('x'), 'is_active' => true]);
        $csv = "title,keywords,thumbnail,image_style\nMy Post,alpha,yes,flat art\n";
        Storage::disk('local')->put('ai-imports/img.csv', $csv);

        $batch = \App\Models\AiImportBatch::create(['name' => 'B', 'kind' => 'blog', 'csv_path' => 'ai-imports/img.csv', 'prompt' => 'brief', 'provider' => 'anthropic', 'user_id' => $author->id]);
        (new \App\Services\Ai\BlogPlanner)->plan($batch);

        $row = $batch->items()->first()->row;
        $this->assertSame('yes', $row['generate_image']); // "thumbnail" column aliased
        $this->assertSame('flat art', $row['image_style']);
    }

    public function test_remote_update_is_rejected_when_disabled(): void
    {
        Setting::set('network.allow_remote_update', false);
        \Illuminate\Support\Facades\Cache::forget('settings.network');

        [$key, $secret] = NetworkIdentity::ensure();
        $headers = NetworkSignature::headers($key, $secret, 'POST', 'api/v1/network/update', '', 'n'.bin2hex(random_bytes(8)), time());
        $server = ['HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        $this->call('POST', '/api/v1/network/update', [], [], [], $server)
            ->assertStatus(403)
            ->assertJson(['ok' => false]);
    }
}
