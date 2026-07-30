<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A small amount of demo blog content so a fresh Hemdox Blog Kit install has
 * something to render on the blog index, category and post pages. Idempotent
 * (keyed by slug). Only meaningful with the ecommerce module off — the store
 * ships DemoCatalogSeeder instead.
 */
class BlogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'admin@example.com')->first()
            ?? User::first();

        if (! $author) {
            return; // nothing to attribute posts to
        }

        $categories = [
            ['slug' => 'guides', 'name' => 'Guides', 'description' => 'Step-by-step how-tos and explainers.'],
            ['slug' => 'news', 'name' => 'News', 'description' => 'Announcements and updates.'],
            ['slug' => 'stories', 'name' => 'Stories', 'description' => 'Longer reads and perspectives.'],
        ];

        $cats = [];
        foreach ($categories as $c) {
            $cats[$c['slug']] = PostCategory::updateOrCreate(['slug' => $c['slug']], $c);
        }

        $posts = [
            [
                'slug' => 'welcome-to-hemdox-blog-kit',
                'title' => 'Welcome to Hemdox Blog Kit',
                'category' => 'news',
                'excerpt' => 'A fast, secure, SEO- and AI-optimized publishing platform — this is your first post.',
                'content' => '<h2>Hello and welcome</h2><p>This is a demo post created during setup. Hemdox Blog Kit gives you a full editor, SEO controls, an AI blog writer, an idea generator, a media library, and a security center — all out of the box.</p><h2>What to do next</h2><p>Head to <strong>Admin → Content → Blog posts</strong> to write your own, or delete this one. Everything here is yours to customize.</p>',
                'days_ago' => 0,
            ],
            [
                'slug' => 'writing-seo-friendly-posts',
                'title' => 'Writing SEO-friendly posts that also read well',
                'category' => 'guides',
                'excerpt' => 'A quick guide to structuring articles for search engines and AI answer engines without sacrificing readability.',
                'content' => '<h2>Start with intent</h2><p>Write for a real question your reader is asking. Lead with a clear, self-contained answer in the first paragraph.</p><h2>Structure for scanners and machines</h2><p>Use descriptive H2s phrased as questions, keep paragraphs short, and add an FAQ section. Hemdox Blog Kit emits the right schema.org markup automatically.</p><h2>Link with purpose</h2><p>Use the built-in internal link agent to connect related posts with descriptive anchors.</p>',
                'days_ago' => 2,
            ],
            [
                'slug' => 'the-story-behind-the-blog',
                'title' => 'The story behind starting a blog',
                'category' => 'stories',
                'excerpt' => 'Why publishing your own words on your own platform still matters.',
                'content' => '<h2>Own your audience</h2><p>Social platforms come and go, but a blog you control is a durable home for your ideas.</p><h2>Compounding returns</h2><p>Every post you publish keeps working for you — found through search, shared by readers, and cited by AI assistants long after you hit publish.</p>',
                'days_ago' => 5,
            ],
        ];

        foreach ($posts as $p) {
            $words = str_word_count(strip_tags($p['content']));

            Post::updateOrCreate(
                ['slug' => $p['slug']],
                [
                    'author_id' => $author->id,
                    'post_category_id' => $cats[$p['category']]->id ?? null,
                    'title' => $p['title'],
                    'excerpt' => $p['excerpt'],
                    'content' => $p['content'],
                    'reading_time' => max(1, (int) ceil($words / 200)),
                    'show_toc' => true,
                    'status' => 'published',
                    'published_at' => now()->subDays($p['days_ago']),
                ],
            );
        }
    }
}
