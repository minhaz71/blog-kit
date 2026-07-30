<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

class HomepageSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'type' => 'hero',
                'title' => 'Better tools for a modern life',
                'subtitle' => 'Curated products from brands we trust. Free returns for 30 days.',
                'sort_order' => 10,
                'settings' => [
                    'button_text' => 'Shop the collection',
                    'button_url' => '/shop',
                    'overlay_opacity' => 30,
                ],
            ],
            [
                'type' => 'trust_badges',
                'title' => 'Why shop with us',
                'sort_order' => 20,
                'settings' => [
                    'items' => [
                        ['label' => 'Free shipping', 'sub_label' => 'On orders $100+'],
                        ['label' => '30-day returns', 'sub_label' => 'No questions asked'],
                        ['label' => 'Secure checkout', 'sub_label' => 'SSL + Stripe'],
                        ['label' => 'Real support', 'sub_label' => 'Reply in 24h'],
                    ],
                ],
            ],
            [
                'type' => 'category_grid',
                'title' => 'Shop by category',
                'sort_order' => 30,
                'settings' => ['category_slugs' => []],
            ],
            [
                'type' => 'featured_products',
                'title' => 'Featured picks',
                'sort_order' => 40,
                'settings' => ['limit' => 8],
            ],
            [
                'type' => 'on_sale',
                'title' => 'On sale now',
                'sort_order' => 50,
                'settings' => ['limit' => 8],
            ],
            [
                'type' => 'testimonials',
                'title' => 'Loved by our customers',
                'sort_order' => 60,
                'settings' => [
                    'items' => [
                        ['author' => 'Priya S.', 'location' => 'Mumbai', 'quote' => 'Great quality and blazing-fast delivery — beat my expectations.'],
                        ['author' => 'Diego M.', 'location' => 'Madrid', 'quote' => 'Support replied in minutes and sorted the size swap instantly.'],
                        ['author' => 'Alex R.', 'location' => 'Toronto', 'quote' => 'Site is snappy, checkout was two clicks. Ordered from mobile.'],
                    ],
                ],
            ],
            [
                'type' => 'blog_posts',
                'title' => 'From the journal',
                'sort_order' => 70,
                'settings' => ['limit' => 3],
            ],
            [
                'type' => 'newsletter',
                'title' => 'Get the newsletter',
                'sort_order' => 80,
                'settings' => [
                    'description' => 'Product drops, restock alerts, and the occasional deep discount.',
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
