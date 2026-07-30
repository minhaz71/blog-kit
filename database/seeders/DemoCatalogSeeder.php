<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SeoMeta;
use App\Models\ShippingClass;
use App\Models\ShippingMethod;
use App\Models\ShippingZone;
use App\Models\Tag;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Brands
        $brands = collect(['Aurora', 'Northwind', 'Kestrel', 'Marlow', 'Hemlock'])->map(function ($name) {
            return Brand::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => "$name is a premium lifestyle brand.", 'is_active' => true],
            );
        });

        // Shipping classes
        $lightClass = ShippingClass::updateOrCreate(['slug' => 'light'], ['name' => 'Light', 'extra_cost' => 0]);
        $bulkyClass = ShippingClass::updateOrCreate(['slug' => 'bulky'], ['name' => 'Bulky', 'extra_cost' => 10]);

        // Categories (parent + children)
        $catData = [
            'Apparel' => ['T-Shirts', 'Jackets', 'Shoes'],
            'Home' => ['Kitchen', 'Bedroom', 'Decor'],
            'Electronics' => ['Audio', 'Wearables', 'Accessories'],
            'Outdoor' => ['Camping', 'Cycling', 'Hiking'],
        ];

        $categories = collect();

        foreach ($catData as $parentName => $children) {
            $parent = Category::updateOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'description' => "Shop our {$parentName} collection.",
                    'is_active' => true,
                    'sort_order' => 0,
                ],
            );
            $categories->push($parent);

            SeoMeta::updateOrCreate(
                ['metable_type' => Category::class, 'metable_id' => $parent->id],
                [
                    'title' => "$parentName — Curated Collection at ShopKit",
                    'description' => "Explore the best in $parentName at ShopKit. Fast delivery, top brands, and easy returns.",
                    'focus_keyword' => strtolower($parentName),
                    'schema_enabled' => true,
                ],
            );
            $parent->touch();

            foreach ($children as $i => $childName) {
                $child = Category::updateOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name' => $childName,
                        'parent_id' => $parent->id,
                        'description' => "Browse the $childName selection.",
                        'is_active' => true,
                        'sort_order' => $i,
                    ],
                );
                $categories->push($child);
            }
        }

        // Attributes (Color, Size)
        $color = Attribute::updateOrCreate(['slug' => 'color'], ['name' => 'Color', 'type' => 'color']);
        foreach (['Black' => '#111111', 'White' => '#ffffff', 'Navy' => '#1e2a4a', 'Olive' => '#556b2f'] as $val => $hex) {
            AttributeValue::updateOrCreate(
                ['attribute_id' => $color->id, 'slug' => Str::slug($val)],
                ['value' => $val, 'color_code' => $hex],
            );
        }
        $size = Attribute::updateOrCreate(['slug' => 'size'], ['name' => 'Size', 'type' => 'select']);
        foreach (['S', 'M', 'L', 'XL'] as $i => $val) {
            AttributeValue::updateOrCreate(
                ['attribute_id' => $size->id, 'slug' => Str::slug($val)],
                ['value' => $val, 'sort_order' => $i],
            );
        }

        // Tags
        $tags = collect(['New', 'Sale', 'Trending', 'Eco', 'Handmade'])->map(fn ($t) => Tag::updateOrCreate(
            ['slug' => Str::slug($t)],
            ['name' => $t],
        ));

        // Products (20 demo)
        $adjectives = ['Classic', 'Modern', 'Everyday', 'Weekend', 'Pro', 'Signature', 'Original', 'Essential'];
        $nouns = ['Tee', 'Hoodie', 'Jacket', 'Sneaker', 'Headphones', 'Watch', 'Lamp', 'Backpack', 'Mug', 'Bottle'];

        $products = collect();
        for ($i = 1; $i <= 20; $i++) {
            $name = $adjectives[array_rand($adjectives)].' '.$nouns[array_rand($nouns)].' '.$i;
            $slug = Str::slug($name);
            $price = mt_rand(1500, 15000) / 100;
            $onSale = $i % 3 === 0;
            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'brand_id' => $brands->random()->id,
                    'shipping_class_id' => $i % 5 === 0 ? $bulkyClass->id : $lightClass->id,
                    'type' => 'simple',
                    'name' => $name,
                    'sku' => 'SKU-'.strtoupper(Str::random(6)),
                    'short_description' => "A great $name for every day.",
                    'description' => "<p>The <strong>$name</strong> is built with quality materials and thoughtful design. Perfect for daily use.</p><p>Features durability, comfort, and modern styling.</p>",
                    'price' => $price,
                    'sale_price' => $onSale ? round($price * 0.8, 2) : null,
                    'manage_stock' => true,
                    'stock_qty' => mt_rand(0, 50),
                    'stock_status' => 'in_stock',
                    'low_stock_threshold' => 5,
                    'weight' => mt_rand(1, 20) / 10,
                    'is_featured' => $i <= 6,
                    'is_new_arrival' => $i > 15,
                    'is_best_seller' => $i % 4 === 0,
                    'visibility' => 'visible',
                    'status' => 'published',
                    'tax_class' => 'standard',
                    'published_at' => now()->subDays(mt_rand(0, 30)),
                ],
            );

            $product->categories()->syncWithoutDetaching([$categories->random()->id]);
            $product->tags()->syncWithoutDetaching([$tags->random()->id]);

            SeoMeta::updateOrCreate(
                ['metable_type' => Product::class, 'metable_id' => $product->id],
                [
                    'title' => "$name — Buy Online",
                    'description' => "Shop the $name at ShopKit with fast shipping and easy returns. Try it risk-free.",
                    'focus_keyword' => strtolower(explode(' ', $name)[1] ?? $name),
                    'schema_enabled' => true,
                ],
            );

            // Refresh product so the SEO observer runs the analyzer with the meta above.
            $product->touch();

            $products->push($product);
        }

        // Shipping zone + methods
        $zone = ShippingZone::updateOrCreate(
            ['name' => 'Domestic'],
            ['countries' => ['US'], 'is_active' => true, 'sort_order' => 0],
        );

        ShippingMethod::updateOrCreate(
            ['shipping_zone_id' => $zone->id, 'type' => 'flat_rate'],
            ['title' => 'Standard Delivery', 'cost' => 5.99, 'delivery_estimate' => '3-5 business days', 'is_active' => true, 'sort_order' => 1],
        );
        ShippingMethod::updateOrCreate(
            ['shipping_zone_id' => $zone->id, 'type' => 'free_shipping'],
            ['title' => 'Free Shipping', 'cost' => 0, 'min_order_amount' => 100, 'delivery_estimate' => '3-5 business days', 'is_active' => true, 'sort_order' => 2],
        );
        ShippingMethod::updateOrCreate(
            ['shipping_zone_id' => $zone->id, 'type' => 'local_pickup'],
            ['title' => 'Local Pickup', 'cost' => 0, 'delivery_estimate' => 'Ready same day', 'is_active' => true, 'sort_order' => 3],
        );

        // Tax rate
        TaxRate::updateOrCreate(
            ['country' => 'US', 'tax_class' => 'standard'],
            ['name' => 'US Sales Tax', 'rate' => 7.5, 'is_active' => true, 'priority' => 1],
        );

        // Pages
        $pageData = [
            ['About Us', 'about-us', '<h2>About ShopKit</h2><p>We are a modern ecommerce store built for speed and great service.</p>', true],
            ['Contact Us', 'contact-us', '<p>Questions about an order or product? Send us a message.</p>{{contact_info}}{{contact_form}}', true],
            ['Privacy Policy', 'privacy-policy', '<p>We only collect what we need to deliver your order and run the store. We never sell your data.</p><h2>What We Collect</h2><p>Order and contact details, age confirmation, and technical data. We do not store card numbers.</p><h2>Your Rights</h2><p>You can view, correct or delete your data, or opt out of marketing, any time.</p>', true],
            ['Terms and Conditions', 'terms-and-conditions', '<p>By browsing this site or placing an order you agree to these terms. Products are sold to adults aged 18+ only and contain nicotine.</p><h2>Age Restriction</h2><p>You confirm you are at least 18 years old. We may ask for ID on delivery.</p><h2>Orders and Delivery</h2><p>Prices are in AED. We accept cash or card on delivery. Delivery is free on orders over AED 300.</p>', true],
            ['Return and Refund Policy', 'refund-policy', '<p>If anything is wrong with your order we will make it right. Report issues within 24 hours of delivery.</p><h2>What Can Be Returned</h2><p>Wrong, damaged, incomplete or faulty items. Sealed tobacco cannot be returned once opened, for health and safety reasons.</p><h2>Refunds</h2><p>Choose a replacement, store credit, or a refund to your original payment method (card refunds take 5 to 10 business days).</p>', true],
            ['Shipping and Delivery Policy', 'shipping-policy', '<p>Dubai, Sharjah and Ajman: 1 to 2 hours. Abu Dhabi, Al Ain, RAK, UAQ: same day or within 12 hours.</p><h2>Charges</h2><p>Free over AED 300; a standard charge under that, shown at checkout. Cash or card on delivery.</p><h2>Receiving (18+)</h2><p>An adult aged 18 or over must receive the order; the courier may check ID.</p>', true],
            ['FAQ', 'faq', '<h2>Frequently Asked Questions</h2><p>Find answers to common questions below.</p>', true],
        ];

        foreach ($pageData as [$title, $slug, $content, $system]) {
            Page::updateOrCreate(
                ['slug' => $slug],
                ['title' => $title, 'content' => $content, 'status' => 'published', 'is_system' => $system, 'template' => 'default'],
            );
        }

        // Post categories + posts
        $postCats = collect(['Guides', 'News', 'Product Updates'])->map(function ($n) {
            return PostCategory::updateOrCreate(
                ['slug' => Str::slug($n)],
                ['name' => $n, 'description' => "$n from our team."],
            );
        });

        $authorId = User::query()->min('id') ?? User::factory()->create(['name' => 'Editor', 'email' => 'editor@example.com'])->id;

        for ($i = 1; $i <= 6; $i++) {
            $title = "Sample Post {$i}: Getting the Most from Your ShopKit Store";
            $slug = Str::slug($title);
            Post::updateOrCreate(
                ['slug' => $slug],
                [
                    'author_id' => $authorId,
                    'post_category_id' => $postCats->random()->id,
                    'title' => $title,
                    'excerpt' => 'A quick walkthrough of practical tips and tricks.',
                    'content' => '<h2>Introduction</h2><p>Welcome to our blog.</p><h2>Details</h2><p>Here are practical tips to level up your store.</p><h2>Wrap up</h2><p>Thanks for reading.</p>',
                    'reading_time' => 4,
                    'show_toc' => true,
                    'status' => 'published',
                    'published_at' => now()->subDays($i),
                ],
            );
        }

        // Global FAQs (attached to About page for demo)
        $about = Page::firstWhere('slug', 'about-us');
        if ($about) {
            $faqs = [
                ['Do you ship internationally?', 'Yes, we ship to select countries. See our shipping page for details.'],
                ['What is your return policy?', 'Returns are accepted within 30 days of delivery.'],
                ['Do you offer bulk discounts?', 'Yes. Contact us for custom volume pricing.'],
            ];
            foreach ($faqs as $i => [$q, $a]) {
                Faq::updateOrCreate(
                    ['faqable_type' => Page::class, 'faqable_id' => $about->id, 'question' => $q],
                    ['answer' => $a, 'sort_order' => $i, 'is_active' => true],
                );
            }
        }
    }
}
