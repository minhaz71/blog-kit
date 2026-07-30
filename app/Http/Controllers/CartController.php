<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Cart\CartService;
use App\Services\Cart\CouponService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CouponService $coupons,
    ) {}

    public function index()
    {
        $cart = $this->cart->current(false);
        $discount = 0.0;

        if ($cart?->coupon) {
            try {
                $this->coupons->validate($cart->coupon, $cart, auth()->user());
                $discount = $this->coupons->discountFor($cart->coupon, $cart);
            } catch (ValidationException) {
                $cart->update(['coupon_id' => null]);
                $cart->refresh();
            }
        }

        return view('cart.index', [
            'cart' => $cart,
            'discount' => $discount,
        ]);
    }

    /**
     * Capture the shopper's contact details onto their live cart so an
     * abandoned-cart reminder can reach them later — this is the ONLY way a
     * guest (session-keyed, no account) becomes reachable. Called from the
     * checkout email field. Never creates an empty cart (no items → nothing to
     * recover), and only ever fills contact columns.
     */
    public function identify(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $cart = $this->cart->current(false);

        if ($cart) {
            $cart->forceFill([
                'email' => $data['email'],
                'customer_name' => $data['name'] ?: $cart->customer_name,
                'phone' => $data['phone'] ?: $cart->phone,
            ])->save();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Restore an abandoned cart from the reminder-email link. Guarded by the
     * per-cart HMAC code (no login needed, tamper-proof). "Adopts" the cart
     * into the current browser session (or the logged-in user) so it reloads
     * even on a different device or after the original session expired, then
     * sends the shopper to the cart page.
     */
    public function restore(Request $request, \App\Models\Cart $cart, string $code)
    {
        abort_unless($cart->verifyRecoveryCode($code), 404);

        // Only restore a still-open cart with items; a converted/empty one just
        // lands on the cart page.
        if ($cart->status === 'active' && $cart->items()->exists()) {
            $cart->forceFill(auth()->check()
                ? ['user_id' => auth()->id()]
                : ['session_id' => $request->session()->getId()]
            )->save(); // touches updated_at → shopper is active again, sequence re-anchors
        }

        return redirect()->route('cart.index');
    }

    public function drawer()
    {
        // current() already eager-loads items.product + the full
        // variation.attributeValues.attribute chain — a ->load() here would
        // re-query and DROP the nested attribute load, N+1ing variation->label().
        return view('partials.cart-drawer-content', [
            'cart' => $this->cart->current(false),
        ]);
    }

    /**
     * Cart badge hydration for cached pages: cached guest HTML always ships
     * cartCount: 0 and fetches the real count from here (cart* paths are
     * never page-cached, so this reflects the live session).
     */
    public function count()
    {
        // token: cached pages carry another visitor's CSRF token — the
        // frontend swaps in this session's token so add-to-cart never 419s.
        // Same-origin only (CORS), so exposing it here is safe.
        return response()
            ->json([
                'count' => $this->cart->current(false)?->itemCount() ?? 0,
                'token' => csrf_token(),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variation_id' => ['nullable', 'integer', 'exists:product_variations,id'],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variation = isset($data['variation_id']) ? ProductVariation::findOrFail($data['variation_id']) : null;

        $this->cart->add($product, (int) $data['qty'], $variation);

        if ($request->expectsJson()) {
            $cart = $this->cart->current();

            return response()->json(['message' => 'Added to cart.', 'count' => $cart->itemCount()]);
        }

        return redirect()->route('cart.index')->with('success', 'Added to cart.');
    }

    public function update(Request $request, int $item)
    {
        $data = $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:999']]);

        $this->cart->updateQty($item, (int) $data['qty']);

        if ($request->expectsJson()) {
            return $this->cartStateJson();
        }

        return back()->with('success', 'Cart updated.');
    }

    public function remove(Request $request, int $item)
    {
        $this->cart->remove($item);

        if ($request->expectsJson()) {
            return $this->cartStateJson();
        }

        return back()->with('success', 'Item removed.');
    }

    /** Shared JSON payload every AJAX quantity/remove caller (drawer, cart, checkout) reads. */
    protected function cartStateJson()
    {
        $cart = $this->cart->current(false);
        $subtotal = $cart ? $cart->subtotal() : 0.0;

        return response()->json([
            'count' => $cart ? $cart->itemCount() : 0,
            'empty' => ! $cart || $cart->items->isEmpty(),
            'subtotal' => price_format($subtotal),
        ]);
    }

    public function applyCoupon(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);

        $cart = $this->cart->current(false);

        if (! $cart || $cart->items->isEmpty()) {
            return back()->withErrors(['coupon' => 'Your cart is empty.']);
        }

        $coupon = Coupon::where('code', strtoupper(trim($data['code'])))->first();

        if (! $coupon) {
            return back()->withErrors(['coupon' => 'Invalid coupon code.']);
        }

        $this->coupons->validate($coupon, $cart, auth()->user());
        $cart->update(['coupon_id' => $coupon->id]);

        return back()->with('success', 'Coupon applied.');
    }

    public function removeCoupon()
    {
        $this->cart->current(false)?->update(['coupon_id' => null]);

        return back()->with('success', 'Coupon removed.');
    }
}
