<?php

namespace App\Http\Requests;

use App\Payments\PaymentManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // guest checkout is allowed
    }

    protected function prepareForValidation(): void
    {
        // "Billing same as shipping" — server-side fallback if JS didn't mirror the fields.
        if ($this->boolean('billing_same') && is_array($this->input('shipping'))) {
            $this->merge(['billing' => $this->input('shipping')]);
        }
    }

    public function rules(): array
    {
        // Address rules come from the merchant's checkout config (Admin →
        // Checkout) so the server enforces exactly what the form shows: a
        // hidden field is not required, and only countries the store sells to
        // are accepted (a tampered request must not order elsewhere).
        $addressRules = \App\Support\CheckoutFields::addressRules(array_keys(store_countries()));

        $meta = \App\Support\CheckoutFields::meta();
        $phoneRules = $meta['phone_enabled'] && $meta['phone_required']
            ? ['required', 'string', 'max:30']
            : ['nullable', 'string', 'max:30'];

        return [
            'email' => ['required', 'email', 'max:255'],
            'phone' => $phoneRules,
            'note' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['required', 'string', Rule::in(app(PaymentManager::class)->keys())],
            'shipping_method_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'billing' => ['required', 'array'],
            'shipping' => ['required', 'array'],
            ...collect($addressRules)->mapWithKeys(fn ($rules, $field) => ["billing.{$field}" => $rules])->all(),
            ...collect($addressRules)->mapWithKeys(fn ($rules, $field) => ["shipping.{$field}" => $rules])->all(),
        ];
    }

    /** Use the merchant's custom labels in validation error messages. */
    public function attributes(): array
    {
        $attrs = [];
        foreach (\App\Support\CheckoutFields::fields() as $field => $cfg) {
            $attrs["shipping.{$field}"] = strtolower($cfg['label']);
            $attrs["billing.{$field}"] = 'billing '.strtolower($cfg['label']);
        }

        return $attrs;
    }
}
