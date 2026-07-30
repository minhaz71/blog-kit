<?php

namespace App\Payments;

use App\Models\PaymentMethod;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Gateways\OfflineGateway;
use App\Payments\Gateways\PayPalGateway;
use App\Payments\Gateways\StripeGateway;
use InvalidArgumentException;

/**
 * Resolves payment gateways from two sources:
 *  - coded ONLINE gateways (Stripe, PayPal) configured in Payment settings;
 *  - admin-defined OFFLINE methods (cash/card on delivery, bank transfer…)
 *    stored in the payment_methods table and adapted via OfflineGateway.
 *
 * Offline methods are fully editable in the admin (name, message, surcharge)
 * and are the primary way to offer pay-on-delivery options.
 */
class PaymentManager
{
    /** @var array<string, class-string<PaymentGateway>> */
    protected array $coded = [
        'stripe' => StripeGateway::class,
        'paypal' => PayPalGateway::class,
    ];

    /** Register an additional coded gateway driver (e.g. a local processor). */
    public function extend(string $key, string $class): void
    {
        $this->coded[$key] = $class;
    }

    public function gateway(string $key): PaymentGateway
    {
        if (isset($this->coded[$key])) {
            return app($this->coded[$key]);
        }

        if ($method = PaymentMethod::where('key', $key)->first()) {
            return new OfflineGateway($method);
        }

        throw new InvalidArgumentException("Unknown payment gateway [{$key}].");
    }

    /** All enabled gateways: active offline methods first (checkout order), then online. */
    public function enabled(): array
    {
        $offline = PaymentMethod::activeMethods()
            ->map(fn (PaymentMethod $m) => new OfflineGateway($m))
            ->all();

        $online = collect($this->coded)
            ->keys()
            ->map(fn (string $key) => $this->gateway($key))
            ->filter(fn (PaymentGateway $g) => $g->isEnabled())
            ->values()
            ->all();

        return array_merge($offline, $online);
    }

    public function isEnabled(string $key): bool
    {
        if (isset($this->coded[$key])) {
            return $this->gateway($key)->isEnabled();
        }

        return PaymentMethod::where('key', $key)->where('is_active', true)->exists();
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_merge(
            array_keys($this->coded),
            PaymentMethod::pluck('key')->all(),
        );
    }
}
