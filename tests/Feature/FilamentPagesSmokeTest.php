<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every admin resource/page as a Super Admin to catch classes that
 * fail to load their forms (e.g. wrong namespace on a Section import).
 */
class FilamentPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected array $urls = [
        '/admin',
        '/admin/products',
        '/admin/categories',
        '/admin/brands',
        '/admin/tags',
        '/admin/attributes',
        '/admin/orders',
        '/admin/customers',
        '/admin/coupons',
        '/admin/reviews',
        '/admin/shipping-zones',
        '/admin/shipping-classes',
        '/admin/tax-rates',
        '/admin/payment-rules',
        '/admin/payment-methods',
        '/admin/payment-methods/create',
        '/admin/homepage-sections',
        '/admin/content-blocks',
        '/admin/product-templates',
        '/admin/product-templates/create',
        '/admin/pages',
        '/admin/posts',
        '/admin/post-categories',
        '/admin/email-templates',
        '/admin/email-logs',
        '/admin/redirects',
        '/admin/not-found-logs',
        '/admin/broken-links',
        '/admin/blocked-ips',
        '/admin/firewall-logs',
        '/admin/login-attempts',
        '/admin/audit-logs',
        '/admin/file-scan-results',
        '/admin/staff-users',
        '/admin/roles',
        '/admin/roles/create',
        '/admin/subscribers',
        '/admin/contact-messages',
        '/admin/backups',
        // Create forms — exercise rich editors, source-code actions, custom-code tabs.
        '/admin/products/create',
        '/admin/categories/create',
        '/admin/posts/create',
        '/admin/pages/create',
        // Settings pages — the ones that just broke.
        '/admin/general-settings',
        '/admin/ai-settings',
        '/admin/ai-usage-dashboard',
        '/admin/ai-call-queues',
        '/admin/ai-import-batches',
        '/admin/ai-import-batches/create',
        '/admin/ai-blog-batches',
        '/admin/ai-blog-batches/create',
        '/admin/appearance-settings',
        '/admin/navigation-settings',
        '/admin/seo-settings',
        '/admin/link-agent',
        '/admin/seo-editor',
        '/admin/internal-links-report',
        '/admin/image-seo-tools',
        '/admin/media-library',
        '/admin/search-performance',
        '/admin/page-speed-report',
        '/admin/payment-settings',
        '/admin/security-settings',
        '/admin/security-center',
        '/admin/system-updates',
        '/admin/email-settings',
        '/admin/performance-settings',
        '/admin/abandoned-carts',
        '/admin/abandoned-cart-settings',
        '/admin/find-replace',
        // Network (multisite) — hub-only screens (test env sets role=hub).
        '/admin/connected-sites',
        '/admin/connected-sites/create',
        '/admin/network-settings',
    ];

    public function test_every_admin_page_renders_without_error(): void
    {
        // Full seed so list pages render real rows — per-row closures
        // (badge colors, formatters) only evaluate when records exist.
        $this->seed();
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('Super Admin');
        $this->actingAs($user);

        $failed = [];
        foreach ($this->urls as $url) {
            $response = $this->get($url);
            if ($response->status() !== 200) {
                $failed[] = $url.' → '.$response->status();
            }
        }

        // Edit pages fill forms from real records — a different failure
        // surface than list/create (e.g. bad relationship() bindings).
        $order = \App\Models\Order::query()->first() ?? \App\Models\Order::create([
            'order_number' => \App\Models\Order::generateOrderNumber(),
            'status' => 'pending',
            'currency' => 'USD',
            'subtotal' => 100, 'shipping_total' => 0, 'tax_total' => 0, 'total' => 100,
            'payment_method' => 'cash_on_delivery', 'payment_status' => 'pending',
            'billing_address' => ['first_name' => 'Smoke', 'last_name' => 'Test'],
            'shipping_address' => ['first_name' => 'Smoke', 'last_name' => 'Test'],
            'customer_email' => 'smoke@example.com',
        ]);

        $editTargets = [
            'orders' => $order,
            'products' => \App\Models\Product::query()->first(),
            'categories' => \App\Models\Category::query()->first(),
            'posts' => \App\Models\Post::query()->first(),
            'pages' => \App\Models\Page::query()->first(),
            'homepage-sections' => \App\Models\HomepageSection::query()->first(),
            'content-blocks' => \App\Models\ContentBlock::query()->first(),
            'email-templates' => \App\Models\EmailTemplate::query()->first(),
            'coupons' => \App\Models\Coupon::query()->first() ?? \App\Models\Coupon::create([
                'code' => 'SMOKETEST',
                'type' => 'percent',
                'value' => 10,
                'is_active' => true,
            ]),
        ];

        foreach ($editTargets as $slug => $record) {
            if ($record === null) {
                $failed[] = "/admin/{$slug}/*/edit → no seeded record to test";

                continue;
            }

            $response = $this->get("/admin/{$slug}/{$record->getKey()}/edit");
            if ($response->status() !== 200) {
                $failed[] = "/admin/{$slug}/{$record->getKey()}/edit → ".$response->status();
            }
        }

        $this->assertEmpty($failed, "Failed URLs:\n".implode("\n", $failed));
    }
}
