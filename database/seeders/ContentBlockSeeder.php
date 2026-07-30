<?php

namespace Database\Seeders;

use App\Models\ContentBlock;
use Illuminate\Database\Seeder;

class ContentBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            [
                'key' => 'delivery_notice',
                'name' => 'Delivery notice',
                'type' => 'notice',
                'body' => 'Ships within 24 hours on business days. Free shipping on orders over $100.',
                'is_active' => true,
            ],
            [
                'key' => 'warranty_notice',
                'name' => 'Warranty notice',
                'type' => 'notice',
                'body' => 'Backed by our 1-year manufacturer warranty and a 30-day money-back guarantee.',
                'is_active' => true,
            ],
            [
                'key' => 'return_notice',
                'name' => 'Return policy notice',
                'type' => 'notice',
                'body' => 'Not the right fit? Return any item within 30 days for a full refund — no questions asked.',
                'is_active' => true,
            ],
            [
                'key' => 'shop_cta',
                'name' => 'Shop the collection CTA',
                'type' => 'cta',
                'body' => 'Discover more from our curated collection.',
                'data' => ['button_text' => 'Shop now', 'button_url' => '/shop'],
                'is_active' => true,
            ],
            [
                'key' => 'shipping_faq',
                'name' => 'Shipping FAQ',
                'type' => 'faq',
                'body' => 'Common questions about shipping and delivery.',
                'data' => [
                    'items' => [
                        ['question' => 'How long does delivery take?', 'answer' => 'Standard delivery arrives in 3–5 business days. Express in 1–2 days.'],
                        ['question' => 'Do you ship internationally?', 'answer' => 'Yes — to select countries. Rates are calculated at checkout.'],
                        ['question' => 'Can I track my order?', 'answer' => 'You\'ll receive a tracking link once your order ships.'],
                    ],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($blocks as $b) {
            ContentBlock::updateOrCreate(['key' => $b['key']], $b);
        }
    }
}
