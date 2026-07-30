<?php

namespace App\Console\Commands;

use App\Models\Post;
use Illuminate\Console\Command;

/**
 * The blog schedule cron: flips due "scheduled" posts to "published".
 *
 * Going through the model (not a bulk query) matters: Post::saved events
 * fire per post — sitemap flush, page-cache flush, IndexNow ping and the
 * link agent all react exactly as if the admin clicked Publish.
 */
class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Publish scheduled blog posts whose publish time has arrived';

    public function handle(): int
    {
        $due = Post::query()
            ->where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->get();

        foreach ($due as $post) {
            $post->update(['status' => 'published']);
            $this->info("Published: {$post->title} (scheduled for {$post->published_at})");
        }

        $this->info($due->count().' post(s) published.');

        return self::SUCCESS;
    }
}
