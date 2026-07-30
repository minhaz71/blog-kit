<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'key' => 'new_order_admin',
                'name' => 'New order (admin)',
                'subject' => 'New order #{{order_number}}',
                'heading' => 'New order received — #{{order_number}}',
                'recipient' => 'admin',
                'body' => '<p>A new order (<strong>#{{order_number}}</strong>) has just come in from {{customer_name}} for <strong>{{order_total}}</strong>. Full customer, address and item details are below.</p><p>Open the admin panel to review and process it.</p>',
            ],
            [
                'key' => 'order_confirmed',
                'name' => 'Order confirmation (customer)',
                'subject' => 'Thank you! Your order #{{order_number}} is confirmed',
                'heading' => 'Thank you for your order, {{customer_name}} 🎉',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>Thank you so much for choosing {{store_name}} — we truly appreciate it. We\'re delighted to confirm that your order <strong>#{{order_number}}</strong> (<strong>{{order_total}}</strong>) has been received and our team is already getting it ready for you.</p><p>You\'ll get another email the moment it\'s on its way. If there\'s anything at all we can help with in the meantime, simply reply to this email — we\'re always happy to help.</p><p>Warm regards,<br>The {{store_name}} team</p>',
            ],
            [
                'key' => 'order_processing',
                'name' => 'Order processing',
                'subject' => 'Your order #{{order_number}} is being processed',
                'heading' => 'Your order is on its way to being ready',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>Your order <strong>#{{order_number}}</strong> is now being processed.</p>',
            ],
            [
                'key' => 'order_on_hold',
                'name' => 'Order on hold',
                'subject' => 'Your order #{{order_number}} is on hold',
                'heading' => 'Your order is on hold',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>Your order <strong>#{{order_number}}</strong> is temporarily on hold while we confirm a detail. We\'ll be in touch shortly — no action is needed from you right now.</p>',
            ],
            [
                'key' => 'order_completed',
                'name' => 'Order completed',
                'subject' => 'Your order #{{order_number}} has been delivered',
                'heading' => 'Your order is complete',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>Order <strong>#{{order_number}}</strong> has been completed. We hope you love it!</p>',
            ],
            [
                'key' => 'order_cancelled',
                'name' => 'Order cancelled',
                'subject' => 'Your order #{{order_number}} was cancelled',
                'heading' => 'Order cancelled',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>Your order <strong>#{{order_number}}</strong> has been cancelled. If this wasn\'t you, please contact us.</p>',
            ],
            [
                'key' => 'order_refunded',
                'name' => 'Order refunded',
                'subject' => 'Your refund for order #{{order_number}}',
                'heading' => 'Refund processed',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>A refund of <strong>{{refund_amount}}</strong> for order <strong>#{{order_number}}</strong> has been processed.</p>',
            ],
            [
                'key' => 'order_failed',
                'name' => 'Order failed',
                'subject' => 'Your order #{{order_number}} failed',
                'heading' => 'Payment failed',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>Unfortunately your payment for order <strong>#{{order_number}}</strong> did not go through. Please try again.</p>',
            ],
            [
                'key' => 'low_stock',
                'name' => 'Low stock alert (admin)',
                'subject' => 'Low stock: {{product_name}}',
                'heading' => 'Low stock alert',
                'recipient' => 'admin',
                'body' => '<p>Product <strong>{{product_name}}</strong> is below its low-stock threshold ({{stock_qty}} left).</p>',
            ],
            [
                'key' => 'abandoned_cart',
                'name' => 'Abandoned cart reminder',
                'subject' => 'You left something in your cart 🛒',
                'heading' => 'Still thinking it over, {{customer_name}}?',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>You left {{item_count}} item(s) in your cart at {{store_name}} — and the good news is they\'re still saved for you. Just tap the button below to pick up right where you left off.</p><p>If you have any questions, simply reply to this email — we\'re always happy to help.</p>',
            ],
            [
                'key' => 'review_request',
                'name' => 'Review request',
                'subject' => 'How was your recent order?',
                'heading' => 'Tell us what you think',
                'recipient' => 'customer',
                'body' => '<p>Hi {{customer_name}},</p><p>We\'d love to hear how your recent purchase went. Leaving a quick review helps other shoppers.</p>',
            ],
        ];

        foreach ($templates as $t) {
            EmailTemplate::updateOrCreate(['key' => $t['key']], $t);
        }
    }
}
