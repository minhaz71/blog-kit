<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Services\Seo\SeoAnalyzer;
use Illuminate\Console\Command;

class SchemaRegenerateCommand extends Command
{
    protected $signature = 'schema:regenerate';

    protected $description = 'Re-run the SEO analyzer over every product, category, and post so scores + checks are up-to-date.';

    public function handle(SeoAnalyzer $analyzer): int
    {
        $count = 0;

        foreach (Product::query()->cursor() as $p) {
            $analyzer->analyzeAndStore($p, content: (string) ($p->description ?? ''), h1: (string) $p->name);
            $count++;
        }
        foreach (Category::query()->cursor() as $c) {
            $analyzer->analyzeAndStore($c, content: (string) ($c->description ?? '').' '.(string) ($c->content_block ?? ''), h1: (string) $c->name);
            $count++;
        }
        foreach (Post::query()->cursor() as $post) {
            $analyzer->analyzeAndStore($post, content: (string) ($post->content ?? ''), h1: (string) $post->title);
            $count++;
        }

        $this->info("Re-analyzed {$count} records.");

        return self::SUCCESS;
    }
}
