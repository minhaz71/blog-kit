<?php

namespace App\Support;

/**
 * Single source of truth for the configurable checkout form, driven by the
 * "checkout" settings group (Admin → Checkout). BOTH the checkout Blade view
 * and the PlaceOrderRequest validation read from here, so the visible form
 * and the server-side rules can never drift apart — a field the merchant
 * hides is neither rendered nor required.
 *
 * Core fields (first name, address line 1, city, country) are always present:
 * an order is not fulfillable without them, so they are intentionally NOT
 * disableable. Everything else is merchant-controlled.
 */
class CheckoutFields
{
    /** Bool setting with a default (settings are JSON-cast, so real booleans round-trip). */
    protected static function flag(string $key, bool $default): bool
    {
        $value = setting("checkout.{$key}", $default);

        return is_bool($value) ? $value : (bool) $value;
    }

    /** A custom label override, falling back to the built-in default. */
    protected static function label(string $key, string $default): string
    {
        $value = trim((string) setting("checkout.{$key}", ''));

        return $value !== '' ? $value : $default;
    }

    /**
     * Per-field config for the address block (shared by shipping + billing).
     *
     * @return array<string, array{enabled:bool, required:bool, label:string, core?:bool}>
     */
    public static function fields(): array
    {
        return [
            'first_name'     => ['enabled' => true, 'required' => true, 'label' => self::label('first_name_label', 'First name'), 'core' => true],
            'last_name'      => ['enabled' => self::flag('last_name_enabled', true), 'required' => self::flag('last_name_required', true), 'label' => self::label('last_name_label', 'Last name')],
            'company'        => ['enabled' => self::flag('company_enabled', false), 'required' => false, 'label' => self::label('company_label', 'Company')],
            'address_line_1' => ['enabled' => true, 'required' => true, 'label' => self::label('address_1_label', 'Address'), 'core' => true],
            'address_line_2' => ['enabled' => self::flag('address_2_enabled', true), 'required' => false, 'label' => self::label('address_2_label', 'Apartment, suite')],
            'city'           => ['enabled' => true, 'required' => self::flag('city_required', true), 'label' => self::label('city_label', 'City'), 'core' => true],
            'state'          => ['enabled' => self::flag('state_enabled', true), 'required' => self::flag('state_required', false), 'label' => self::label('state_label', 'State / region')],
            'postal_code'    => ['enabled' => self::flag('postal_code_enabled', true), 'required' => self::flag('postal_code_required', false), 'label' => self::label('postal_code_label', 'Postal code')],
            'country'        => ['enabled' => true, 'required' => true, 'label' => self::label('country_label', 'Country'), 'core' => true],
        ];
    }

    /** Contact + page-level config (phone, order note, headings, notice). */
    public static function meta(): array
    {
        return [
            'phone_enabled'  => self::flag('phone_enabled', true),
            'phone_required' => self::flag('phone_required', false),
            'phone_label'    => self::label('phone_label', 'Phone'),
            'note_enabled'   => self::flag('note_enabled', true),
            'note_label'     => self::label('note_label', 'Order note'),
            'heading'        => self::label('heading', 'Checkout'),
            'subheading'     => self::label('subheading', 'Complete your order below. It only takes a minute.'),
            'notice'         => trim((string) setting('checkout.notice', '')),
            'security_text'  => self::label('security_text', 'Your payment information is encrypted and secure.'),
        ];
    }

    /**
     * Laravel validation rules for one address block, built from the config.
     * A disabled field is accepted-but-ignored (nullable) rather than errored,
     * so a stale/tampered payload never blocks a legitimate order.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function addressRules(array $countryCodes): array
    {
        $rules = [];
        $max = [
            'first_name' => 100, 'last_name' => 100, 'company' => 150,
            'address_line_1' => 200, 'address_line_2' => 200,
            'city' => 100, 'state' => 100, 'postal_code' => 20,
        ];

        foreach (self::fields() as $name => $cfg) {
            if ($name === 'country') {
                $rules['country'] = ['required', 'string', 'size:2', \Illuminate\Validation\Rule::in($countryCodes)];

                continue;
            }
            $length = $max[$name] ?? 200;
            $required = $cfg['enabled'] && $cfg['required'];
            $rules[$name] = [$required ? 'required' : 'nullable', 'string', "max:{$length}"];
        }

        return $rules;
    }
}
