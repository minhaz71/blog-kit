<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    public const TYPES = [
        'hero' => 'Hero banner',
        'usp_strip' => 'USP / delivery promise strip',
        'featured_products' => 'Featured products',
        'best_sellers' => 'Best sellers',
        'new_arrivals' => 'New arrivals',
        'on_sale' => 'On sale',
        'category_grid' => 'Featured categories',
        'category_catalogue' => 'Category catalogue (cards + product counts)',
        'banner' => 'Promotional banner',
        'trust_badges' => 'Trust badges',
        'testimonials' => 'Testimonials',
        'faq' => 'FAQ',
        'cta' => 'Call to action',
        'newsletter' => 'Newsletter signup',
        'blog_posts' => 'Latest blog posts',
        'post_categories' => 'Browse by topic (blog categories)',
        'text_block' => 'Rich text block',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    /** Resolve section settings with a default fallback. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
