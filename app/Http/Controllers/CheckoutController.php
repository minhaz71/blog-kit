<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlaceOrderRequest;
use App\Models\Order;
use App\Payments\Gateways\PayPalGateway;
use App\Payments\PaymentManager;
use App\Services\Cart\CartService;
use App\Services\Cart\CouponService;
use App\Services\Checkout\CheckoutService;
use App\Services\Payments\PaymentRuleService;
use App\Services\Shipping\ShippingCalculator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected CheckoutService $checkout,
        protected PaymentManager $payments,
        protected ShippingCalculator $shipping,
        protected CouponService $coupons,
        protected PaymentRuleService $paymentRules,
    ) {}

    public function index()
    {
        $cart = $this->cart->current(false);

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $title = trim((string) setting('checkout.browser_title')) ?: 'Checkout';

        return view('checkout.index', [
            'cart' => $cart,
            'gateways' => $this->payments->enabled(),
            'discount' => $cart->coupon ? $this->coupons->discountFor($cart->coupon, $cart) : 0.0,
            'defaultShipping' => auth()->user()?->defaultAddress('shipping'),
            'defaultBilling' => auth()->user()?->defaultAddress('billing'),
            // Editable browser <title> (Admin → Checkout settings); noindex utility page.
            'seo' => app(\App\Services\Seo\SeoManager::class)->forUtility($title, noindex: true),
        ]);
    }

    /** AJAX: shipping options + totals for the entered address. */
    public function shippingOptions(Request $request)
    {
        $data = $request->validate([
            'country' => ['required', 'string', 'size:2', \Illuminate\Validation\Rule::in(array_keys(store_countries()))],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_method_id' => ['nullable', 'integer'],
            'payment_method' => ['nullable', 'string'],
        ]);

        $cart = $this->cart->current(false);

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $freeShipping = $cart->coupon ? $this->coupons->grantsFreeShipping($cart->coupon) : false;
        $options = $this->shipping->optionsFor($cart, $data, $freeShipping);

        $methodId = $data['shipping_method_id'] ?? ($options[0]['id'] ?? null);

        $paymentMethod = $data['payment_method'] ?? null;

        try {
            $totals = $this->checkout->totals($cart, $data, $methodId, $paymentMethod);
        } catch (ValidationException) {
            $totals = $this->checkout->totals($cart, $data, $options[0]['id'] ?? null, $paymentMethod);
        }

        // Filter shipping options to only those the payment method allows.
        if ($paymentMethod) {
            $options = $this->paymentRules->filterShippingMethodsForPayment(
                $options, $paymentMethod, $cart, $data,
                auth()->user(), $data['email'] ?? null,
            );
        }

        return response()->json([
            'options' => $options,
            'totals' => array_map(fn ($v) => is_float($v) ? price_format($v) : $v, $totals),
            'selected' => $methodId,
        ]);
    }

    public function store(PlaceOrderRequest $request)
    {
        $cart = $this->cart->current(false);

        if (! $cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $gatewayKey = $request->validated('payment_method');

        if (! $this->payments->isEnabled($gatewayKey)) {
            throw ValidationException::withMessages(['payment_method' => 'This payment method is not available.']);
        }

        $order = $this->checkout->placeOrder($cart, [
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'note' => $request->validated('note'),
            'payment_method' => $gatewayKey,
            'shipping_method_id' => $request->validated('shipping_method_id'),
            'billing_address' => $request->validated('billing'),
            'shipping_address' => $request->validated('shipping'),
            'idempotency_key' => $request->validated('idempotency_key'),
        ]);

        $result = $this->payments->gateway($gatewayKey)->initiate($order);

        if (! $result->success) {
            $order->updateStatus('failed');

            return redirect()->route('checkout.payment-failed', $order->order_number)
                ->with('error', $result->message ?? 'Payment could not be started.');
        }

        if ($result->redirectUrl) {
            return redirect()->away($result->redirectUrl);
        }

        return redirect()->route('checkout.thank-you', $order->order_number);
    }

    public function thankYou(string $orderNumber)
    {
        $order = $this->findOrderForVisitor($orderNumber);

        return view('checkout.thank-you', [
            'order' => $order->load('items'),
            'instructions' => $this->payments->isEnabled((string) $order->payment_method)
                ? $this->payments->gateway((string) $order->payment_method)->instructions()
                : null,
        ]);
    }

    public function failed(string $orderNumber)
    {
        return view('checkout.failed', ['order' => $this->findOrderForVisitor($orderNumber)]);
    }

    /** PayPal approve-and-return flow: capture, then land on thank-you. */
    public function paypalReturn(Request $request, string $orderNumber)
    {
        $order = $this->findOrderForVisitor($orderNumber);

        /** @var PayPalGateway $gateway */
        $gateway = $this->payments->gateway('paypal');
        $paypalOrderId = (string) $request->query('token');

        if ($paypalOrderId === '') {
            return redirect()->route('checkout.payment-failed', $order->order_number);
        }

        $result = $gateway->capture($order, $paypalOrderId);

        return $result->success && $result->paid
            ? redirect()->route('checkout.thank-you', $order->order_number)
            : redirect()->route('checkout.payment-failed', $order->order_number)->with('error', $result->message);
    }

    /**
     * Guests may only see their order in the session-stamped window;
     * logged-in customers must own the order.
     */
    protected function findOrderForVisitor(string $orderNumber): Order
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        if (auth()->check()) {
            abort_unless($order->user_id === auth()->id() || $order->user_id === null, 403);
        } else {
            $recent = session()->get('recent_orders', []);

            if (! in_array($order->order_number, $recent)) {
                // First visit after checkout: stamp it (order placed this session).
                if ($order->created_at->gt(now()->subHours(3)) && $order->ip_address === request()->ip()) {
                    session()->push('recent_orders', $order->order_number);
                } else {
                    abort(403);
                }
            }
        }

        return $order;
    }
}
