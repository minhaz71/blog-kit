<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Terea Hub brand setup: store identity, theme colors, TEREA categories,
 * and the full homepage layout. Everything it writes lives in the database
 * and stays editable from the admin (Homepage sections, Appearance,
 * Navigation, General settings). Safe to re-run.
 */
class TereaHubSeeder extends Seeder
{
    public function run(): void
    {
        $this->brandSettings();
        $categories = $this->categories();
        $this->assignProducts($categories);
        $this->navigation($categories);
        $this->homepage($categories);

        Cache::forget('nav.categories');

        $this->command?->info('Terea Hub storefront content seeded.');
    }

    protected function brandSettings(): void
    {
        // Store identity
        Setting::set('general.site_name', 'Terea Hub');
        Setting::set('general.store_name', 'Terea Hub');
        Setting::set('general.site_tagline', 'IQOS TEREA delivered in 1 hour — Dubai, Sharjah & Ajman');

        // Brand theme — deep premium teal with the IQOS-style turquoise family.
        Setting::set('appearance.primary_color', '#0f766e');
        Setting::set('appearance.primary_hover_color', '#115e59');

        // Announcement bar (site-wide, above the header)
        Setting::set('appearance.announcement_text', '⚡ 1-hour delivery in Dubai, Sharjah & Ajman · 12-hour delivery across the UAE');
        Setting::set('appearance.announcement_url', '/shop');
    }

    /** @return array{uae: Category, japan: Category} */
    protected function categories(): array
    {
        $uae = Category::updateOrCreate(['slug' => 'terea-uae'], [
            'name' => 'TEREA UAE Edition',
            'description' => 'Original IQOS TEREA sticks for the UAE market — Amber, Sienna, Yellow, Bright Wave, Purple Wave, Green Zing and more. Delivered to your door in as little as 1 hour in Dubai, Sharjah and Ajman.',
            'image' => 'categories/terea-uae.svg',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $japan = Category::updateOrCreate(['slug' => 'terea-japan'], [
            'name' => 'TEREA Japan Edition',
            'description' => 'Sought-after Japan-market TEREA flavors — Bold Regular, Warm Regular, Oasis Pearl, Ruby Regular, Black Menthol and more. Genuine imported cartons, delivered across the UAE.',
            'image' => 'categories/terea-japan.svg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return ['uae' => $uae, 'japan' => $japan];
    }

    protected function assignProducts(array $categories): void
    {
        $terea = Product::where('name', 'like', '%terea%')->get();

        foreach ($terea as $product) {
            $isJapan = str_contains(strtolower($product->name), 'japan');
            $category = $isJapan ? $categories['japan'] : $categories['uae'];
            $product->categories()->syncWithoutDetaching([$category->id]);
        }

        // The homepage "featured" rail should showcase the real catalog,
        // not leftover demo products.
        Product::where('is_featured', true)->whereNotIn('id', $terea->pluck('id'))->update(['is_featured' => false]);
        Product::whereIn('id', $terea->pluck('id'))->update(['is_featured' => true]);
    }

    protected function navigation(array $categories): void
    {
        Setting::set('navigation.header_menu', [
            ['label' => 'Shop all', 'url' => '/shop'],
            ['label' => 'TEREA UAE', 'url' => '/category/'.$categories['uae']->slug],
            ['label' => 'TEREA Japan', 'url' => '/category/'.$categories['japan']->slug],
            ['label' => 'Delivery', 'url' => '/shipping-policy'],
            ['label' => 'Blog', 'url' => '/blog'],
        ]);
    }

    protected function homepage(array $categories): void
    {
        // This seeder owns the homepage layout — replace it wholesale.
        HomepageSection::query()->delete();

        $sections = [
            [
                'type' => 'hero',
                'title' => 'Genuine IQOS TEREA, at your door in 1 hour',
                'subtitle' => 'Every UAE and Japan edition flavor, sealed and original. Order now — Dubai, Sharjah & Ajman get it within the hour, everywhere else in the UAE within 12.',
                'sort_order' => 10,
                'settings' => [
                    'badge' => '1-hour delivery — Dubai · Sharjah · Ajman',
                    'image' => 'homepage/hero-desktop.svg',
                    'mobile_image' => 'homepage/hero-mobile.svg',
                    'overlay_opacity' => 25,
                    'button_text' => 'Shop TEREA now',
                    'button_url' => '/shop',
                    'button2_text' => 'Delivery areas',
                    'button2_url' => '/shipping-policy',
                ],
            ],
            [
                'type' => 'usp_strip',
                'title' => 'Why Terea Hub',
                'sort_order' => 15,
                'settings' => [
                    'items' => [
                        ['icon' => 'bolt', 'label' => '1-hour delivery', 'sub_label' => 'Dubai, Sharjah & Ajman'],
                        ['icon' => 'truck', 'label' => '12-hour UAE-wide', 'sub_label' => 'Every emirate, every day'],
                        ['icon' => 'shield', 'label' => '100% genuine', 'sub_label' => 'Sealed, original TEREA only'],
                        ['icon' => 'banknotes', 'label' => 'Pay on delivery', 'sub_label' => 'Cash or card at your door'],
                    ],
                ],
            ],
            [
                'type' => 'category_grid',
                'title' => 'Shop by edition',
                'subtitle' => 'UAE-market flavors for everyday orders, Japan-market editions for the connoisseurs.',
                'sort_order' => 20,
                'settings' => [
                    'category_slugs' => [
                        $categories['uae']->slug => 'TEREA UAE Edition',
                        $categories['japan']->slug => 'TEREA Japan Edition',
                    ],
                ],
            ],
            [
                'type' => 'featured_products',
                'title' => 'Best-selling TEREA flavors',
                'subtitle' => 'The packs our Dubai, Sharjah and Ajman customers reorder the most.',
                'sort_order' => 30,
                'settings' => ['limit' => 8],
            ],
            [
                'type' => 'banner',
                'title' => 'TEREA Japan Edition',
                'subtitle' => 'Bold Regular, Oasis Pearl, Black Menthol — the Japan-exclusive line, delivered anywhere in the UAE.',
                'sort_order' => 40,
                'settings' => [
                    'image' => 'homepage/banner-japan.svg',
                    'link_url' => '/category/terea-japan',
                ],
            ],
            [
                'type' => 'new_arrivals',
                'title' => 'Just landed',
                'subtitle' => 'Fresh stock, added this week.',
                'sort_order' => 50,
                'settings' => ['limit' => 8],
            ],
            [
                'type' => 'testimonials',
                'title' => 'Rated by customers across the UAE',
                'sort_order' => 60,
                'settings' => [
                    'items' => [
                        ['author' => 'Ahmed K.', 'location' => 'Dubai Marina', 'quote' => 'Ordered at 9pm, the rider was at my tower before 10. Sealed packs, exactly as listed. This is my TEREA shop now.'],
                        ['author' => 'Rashid M.', 'location' => 'Sharjah', 'quote' => 'The 1-hour promise is real — 40 minutes to Al Majaz. Paid the courier by card, no drama.'],
                        ['author' => 'Elena V.', 'location' => 'JLT, Dubai', 'quote' => 'Finally found the Japan Black Menthol here. Genuine, fresh date codes, and cheaper than asking friends to fly it in.'],
                        ['author' => 'Omar S.', 'location' => 'Ajman', 'quote' => 'Was sceptical about Ajman being in the 1-hour zone. It is. Great prices on cartons too.'],
                    ],
                ],
            ],
            [
                'type' => 'faq',
                'title' => 'Frequently asked questions',
                'sort_order' => 70,
                'settings' => [
                    'items' => [
                        ['question' => 'How fast is delivery, really?', 'answer' => 'Orders inside Dubai, Sharjah and Ajman are delivered within 1 hour of confirmation. Everywhere else in the UAE — Abu Dhabi, Al Ain, RAK, Fujairah, UAQ — we deliver within 12 hours.'],
                        ['question' => 'Are your TEREA sticks genuine?', 'answer' => 'Yes — we sell only original, factory-sealed IQOS TEREA. UAE-edition packs are sourced locally; Japan-edition cartons are imported directly. Check the seal and date code on arrival before paying.'],
                        ['question' => 'How can I pay?', 'answer' => 'Pay on delivery, your choice of cash or card — our couriers carry card machines. You confirm the products at your door first.'],
                        ['question' => 'What is the difference between UAE and Japan editions?', 'answer' => 'Both are genuine TEREA for IQOS ILUMA. UAE editions are the flavors officially sold here; Japan editions are exclusive flavors from the Japanese market, sold by the carton.'],
                        ['question' => 'Is there a minimum order?', 'answer' => 'We sell full cartons only (10 packs, 200 sticks per carton). Order one carton or several, the delivery promise is the same.'],
                    ],
                ],
            ],
            [
                'type' => 'text_block',
                'title' => 'Buy IQOS TEREA in the UAE',
                'sort_order' => 80,
                'settings' => [
                    'body' => '<p>Terea Hub is a UAE-based store for genuine <strong>IQOS TEREA</strong> sticks, built around one promise: the fastest delivery in the Emirates. Order any UAE-edition flavor — Amber, Sienna, Yellow, Bright Wave, Purple Wave, Green Zing — or Japan-exclusive editions like Bold Regular and Black Menthol, and have them at your door in as little as one hour in Dubai, Sharjah and Ajman, or within twelve hours anywhere in the UAE.</p><p><em>TEREA products contain tobacco and are intended only for adult IQOS ILUMA users aged 18+. Not for sale to minors.</em></p>',
                ],
            ],
            [
                'type' => 'newsletter',
                'title' => 'New flavors land here first',
                'sort_order' => 90,
                'settings' => [
                    'description' => 'Restock alerts, new Japan-edition drops, and subscriber-only carton deals. No spam — you can leave anytime.',
                    'button_text' => 'Notify me',
                ],
            ],
        ];

        foreach ($sections as $s) {
            HomepageSection::create($s + ['is_active' => true]);
        }
    }
}
