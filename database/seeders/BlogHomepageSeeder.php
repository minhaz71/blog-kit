<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

/**
 * Blog-first homepage for Hemdox Blog Kit (ecommerce module off): a hero that
 * points at the blog, the latest posts, and a newsletter sign-up. The
 * ecommerce homepage (category grid, featured products, on-sale…) is seeded
 * by HomepageSeeder instead when the store module is enabled.
 */
class BlogHomepageSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'type' => 'hero',
                'title' => 'Ideas, guides & stories',
                'subtitle' => 'Fresh writing, published fast and optimized for search and AI answer engines.',
                'sort_order' => 10,
                'settings' => [
                    'button_text' => 'Read the blog',
                    'button_url' => '/blog',
                    'overlay_opacity' => 30,
                ],
            ],
            [
                'type' => 'blog_posts',
                'title' => 'Latest from the blog',
                'sort_order' => 20,
                'settings' => ['limit' => 6],
            ],
            [
                'type' => 'post_categories',
                'title' => 'Browse by topic',
                'sort_order' => 25,
                'settings' => ['limit' => 8],
            ],
            [
                'type' => 'newsletter',
                'title' => 'Get new posts by email',
                'sort_order' => 30,
                'settings' => [
                    'description' => 'No spam — just new articles and the occasional deep dive.',
                    'button_text' => 'Subscribe',
                ],
            ],
        ];

        foreach ($sections as $s) {
            HomepageSection::updateOrCreate(
                ['type' => $s['type'], 'sort_order' => $s['sort_order']],
                $s + ['is_active' => true],
            );
        }
    }
}
