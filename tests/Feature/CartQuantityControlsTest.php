<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quantity +/- and remove available from every surface (cart drawer, cart
 * page, checkout) — the drawer/checkout call these endpoints via AJAX and
 * expect a JSON cart-state payload; the cart page keeps working via the
 * plain redirect.
 */
class CartQuantityControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function product(string $slug = 'terea-amber', float $price = 10.0): Product
    {
        return Product::create([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'type' => 'simple',
            'price' => $price, 'stock_status' => 'in_stock', 'stock_qty' => 500,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);
    }

    /**
     * Seed the cart through the real endpoint as a logged-in user, so the
     * cart is keyed by user_id and every follow-up request in the test hits
     * the same cart (guest carts key on session id, which is not stable
     * across separate test requests).
     */
    protected function cartWithItem(Product $product, int $qty = 1): CartItem
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('cart.add'), ['product_id' => $product->id, 'qty' => $qty])->assertOk();

        return CartItem::where('product_id', $product->id)->firstOrFail();
    }

    public function test_update_returns_json_cart_state_for_ajax_callers(): void
    {
        $item = $this->cartWithItem($this->product(), 1);

        $this->patchJson(route('cart.update', $item->id), ['qty' => 3])
            ->assertOk()
            ->assertJson(['count' => 3, 'empty' => false])
            ->assertJsonStructure(['count', 'empty', 'subtotal']);

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'qty' => 3]);
    }

    public function test_remove_returns_json_and_reports_empty(): void
    {
        $item = $this->cartWithItem($this->product(), 2);

        $this->deleteJson(route('cart.remove', $item->id))
            ->assertOk()
            ->assertJson(['count' => 0, 'empty' => true]);

        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
    }

    public function test_over_stock_update_fails_with_422_and_leaves_quantity_unchanged(): void
    {
        $product = Product::create([
            'name' => 'Scarce', 'slug' => 'scarce', 'type' => 'simple', 'price' => 10,
            'stock_status' => 'in_stock', 'stock_qty' => 2, 'manage_stock' => true,
            'status' => 'published', 'visibility' => 'visible', 'published_at' => now(),
        ]);
        $item = $this->cartWithItem($product, 1);

        $this->patchJson(route('cart.update', $item->id), ['qty' => 99])
            ->assertStatus(422);

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'qty' => 1]);
    }

    public function test_cart_page_form_update_still_redirects(): void
    {
        $item = $this->cartWithItem($this->product(), 1);

        // Non-AJAX (form POST) keeps the progressive-enhancement redirect.
        $this->patch(route('cart.update', $item->id), ['qty' => 2])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', ['id' => $item->id, 'qty' => 2]);
    }

    public function test_a_fast_shopper_can_add_many_items_without_being_rate_limited(): void
    {
        // 35 rapid adds would have tripped the old 30/min limit; the
        // per-shopper ceiling is now 60, so a genuine bulk buyer sails through.
        for ($i = 1; $i <= 35; $i++) {
            $p = $this->product("flavor-{$i}");
            $res = $this->postJson(route('cart.add'), ['product_id' => $p->id, 'qty' => 1]);
            $this->assertNotSame(429, $res->status(), "add-to-cart #{$i} was wrongly rate limited");
        }
    }

    public function test_cart_drawer_renders_quantity_stepper_and_remove(): void
    {
        $this->cartWithItem($this->product(), 2);

        $html = $this->get(route('cart.drawer'))->assertOk()->getContent();

        $this->assertStringContainsString('Decrease quantity', $html);
        $this->assertStringContainsString('Increase quantity', $html);
        $this->assertStringContainsString('shopkit.setQty', $html);
        $this->assertStringContainsString('shopkit.removeItem', $html);
        $this->assertStringContainsString('Subtotal', $html);
    }
}
