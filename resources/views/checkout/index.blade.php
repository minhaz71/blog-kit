@extends('layouts.app')

@section('content')
@php
    use App\Support\CheckoutFields;
    $cf = CheckoutFields::fields();     // per-field: enabled, required, label
    $msg = CheckoutFields::meta();      // phone, note, headings, notice, security text
    // Is a field both shown and required? (drives the * marker + required attr)
    $req = fn (string $f) => ($cf[$f]['enabled'] ?? true) && ($cf[$f]['required'] ?? false);

    $field = 'w-full rounded-lg border-gray-300 text-sm shadow-sm transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/30';
    $labelCls = 'mb-1 block text-xs font-medium text-gray-600';
@endphp
<div class="bg-gray-50">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6"
     x-data='checkoutForm()'
     @cart-line-changed="refreshShipping()">

    {{-- Header --}}
    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $msg['heading'] }}</h1>
            @if($msg['subheading'] !== '')
                <p class="mt-1 text-sm text-gray-500">{{ $msg['subheading'] }}</p>
            @endif
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-medium text-gray-600 shadow-sm ring-1 ring-gray-200">
            <svg class="h-3.5 w-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Secure SSL checkout
        </span>
    </div>

    {{-- Merchant notice banner (Admin → Checkout) --}}
    @if($msg['notice'] !== '')
        <div class="mt-4 flex items-start gap-2 rounded-lg border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $msg['notice'] }}</span>
        </div>
    @endif

    <form action="{{ route('checkout.store') }}" method="POST" class="mt-6 grid gap-6 lg:grid-cols-5 lg:items-start" @submit="lock">
        @csrf
        <input type="hidden" name="idempotency_key" :value="idempotencyKey">
        <input type="hidden" name="shipping_method_id" :value="selectedShipping ?? ''">

        <div class="space-y-5 lg:col-span-3">
            {{-- Contact --}}
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">1</span>
                    <h2 class="text-base font-semibold text-gray-900">Contact</h2>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $labelCls }}">Email address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" required value="{{ old('email', auth()->user()?->email) }}" @blur="identify" placeholder="you@example.com" class="{{ $field }}" aria-label="Email">
                    </div>
                    @if($msg['phone_enabled'])
                        <div>
                            <label class="{{ $labelCls }}">{{ $msg['phone_label'] }} @if($msg['phone_required'])<span class="text-red-500">*</span>@endif</label>
                            <input type="tel" name="phone" @required($msg['phone_required']) value="{{ old('phone', auth()->user()?->phone) }}" placeholder="{{ $msg['phone_label'] }}" class="{{ $field }}" aria-label="Phone">
                        </div>
                    @endif
                </div>
                @guest
                    <p class="mt-3 text-sm text-gray-500">Checking out as guest. <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:underline">Sign in</a> for faster checkout.</p>
                @endguest
            </section>

            {{-- Shipping address --}}
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">2</span>
                    <h2 class="text-base font-semibold text-gray-900">Shipping address</h2>
                </div>
                @php $shippingDefault = old('shipping', $defaultShipping?->toOrderArray() ?? []); @endphp
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="{{ $labelCls }}">{{ $cf['first_name']['label'] }} <span class="text-red-500">*</span></label>
                        <input type="text" name="shipping[first_name]" required value="{{ $shippingDefault['first_name'] ?? '' }}" placeholder="{{ $cf['first_name']['label'] }}" class="{{ $field }}">
                    </div>
                    @if($cf['last_name']['enabled'])
                        <div>
                            <label class="{{ $labelCls }}">{{ $cf['last_name']['label'] }} @if($req('last_name'))<span class="text-red-500">*</span>@endif</label>
                            <input type="text" name="shipping[last_name]" @required($req('last_name')) value="{{ $shippingDefault['last_name'] ?? '' }}" placeholder="{{ $cf['last_name']['label'] }}" class="{{ $field }}">
                        </div>
                    @endif
                    @if($cf['company']['enabled'])
                        <div class="sm:col-span-2">
                            <label class="{{ $labelCls }}">{{ $cf['company']['label'] }} <span class="text-gray-400">(optional)</span></label>
                            <input type="text" name="shipping[company]" value="{{ $shippingDefault['company'] ?? '' }}" placeholder="{{ $cf['company']['label'] }}" class="{{ $field }}">
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label class="{{ $labelCls }}">{{ $cf['address_line_1']['label'] }} <span class="text-red-500">*</span></label>
                        <input type="text" name="shipping[address_line_1]" required value="{{ $shippingDefault['address_line_1'] ?? '' }}" placeholder="Street address" class="{{ $field }}">
                    </div>
                    @if($cf['address_line_2']['enabled'])
                        <div class="sm:col-span-2">
                            <label class="{{ $labelCls }}">{{ $cf['address_line_2']['label'] }} <span class="text-gray-400">(optional)</span></label>
                            <input type="text" name="shipping[address_line_2]" value="{{ $shippingDefault['address_line_2'] ?? '' }}" placeholder="{{ $cf['address_line_2']['label'] }}" class="{{ $field }}">
                        </div>
                    @endif
                    <div>
                        <label class="{{ $labelCls }}">{{ $cf['city']['label'] }} @if($req('city'))<span class="text-red-500">*</span>@endif</label>
                        <input type="text" name="shipping[city]" @required($req('city')) value="{{ $shippingDefault['city'] ?? '' }}" placeholder="{{ $cf['city']['label'] }}" class="{{ $field }}" x-model="address.city" @change="refreshShipping">
                    </div>
                    @if($cf['state']['enabled'])
                        <div>
                            <label class="{{ $labelCls }}">{{ $cf['state']['label'] }} @if($req('state'))<span class="text-red-500">*</span>@endif</label>
                            <input type="text" name="shipping[state]" @required($req('state')) value="{{ $shippingDefault['state'] ?? '' }}" placeholder="{{ $cf['state']['label'] }}" class="{{ $field }}" x-model="address.state" @change="refreshShipping">
                        </div>
                    @endif
                    @if($cf['postal_code']['enabled'])
                        <div>
                            <label class="{{ $labelCls }}">{{ $cf['postal_code']['label'] }} @if($req('postal_code'))<span class="text-red-500">*</span>@endif</label>
                            <input type="text" name="shipping[postal_code]" @required($req('postal_code')) value="{{ $shippingDefault['postal_code'] ?? '' }}" placeholder="{{ $cf['postal_code']['label'] }}" class="{{ $field }}" x-model="address.postal_code" @change="refreshShipping">
                        </div>
                    @endif
                    @php
                        $allowedCountries = store_countries();
                        $defaultCountry = array_key_exists($shippingDefault['country'] ?? '', $allowedCountries)
                            ? $shippingDefault['country']
                            : array_key_first($allowedCountries);
                    @endphp
                    <div>
                        <label class="{{ $labelCls }}">{{ $cf['country']['label'] }} <span class="text-red-500">*</span></label>
                        @if(count($allowedCountries) === 1)
                            <input type="hidden" name="shipping[country]" value="{{ $defaultCountry }}">
                            <div class="flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                {{ $allowedCountries[$defaultCountry] }}
                            </div>
                        @else
                            <select name="shipping[country]" required class="{{ $field }}" x-model="address.country" @change="refreshShipping">
                                @foreach($allowedCountries as $code => $countryName)
                                    <option value="{{ $code }}" @selected($defaultCountry === $code)>{{ $countryName }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Billing address --}}
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6" x-data="{ same: true }">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">3</span>
                        <h2 class="text-base font-semibold text-gray-900">Billing address</h2>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-600">
                        <input type="checkbox" x-model="same" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"> Same as shipping
                    </label>
                </div>
                <template x-if="same">
                    <div><input type="hidden" name="billing_same" value="1"></div>
                </template>
                <div class="mt-4 grid gap-4 sm:grid-cols-2" x-show="!same" x-cloak>
                    @php $billingDefault = old('billing', $defaultBilling?->toOrderArray() ?? []); @endphp
                    <div>
                        <label class="{{ $labelCls }}">{{ $cf['first_name']['label'] }} <span class="text-red-500">*</span></label>
                        <input type="text" :name="same ? '' : 'billing[first_name]'" :required="!same" value="{{ $billingDefault['first_name'] ?? '' }}" placeholder="{{ $cf['first_name']['label'] }}" class="{{ $field }}">
                    </div>
                    @if($cf['last_name']['enabled'])
                        <div>
                            <label class="{{ $labelCls }}">{{ $cf['last_name']['label'] }}</label>
                            <input type="text" :name="same ? '' : 'billing[last_name]'" :required="!same && {{ $req('last_name') ? 'true' : 'false' }}" value="{{ $billingDefault['last_name'] ?? '' }}" placeholder="{{ $cf['last_name']['label'] }}" class="{{ $field }}">
                        </div>
                    @endif
                    @if($cf['company']['enabled'])
                        <div class="sm:col-span-2">
                            <label class="{{ $labelCls }}">{{ $cf['company']['label'] }} <span class="text-gray-400">(optional)</span></label>
                            <input type="text" :name="same ? '' : 'billing[company]'" value="{{ $billingDefault['company'] ?? '' }}" placeholder="{{ $cf['company']['label'] }}" class="{{ $field }}">
                        </div>
                    @endif
                    <div class="sm:col-span-2">
                        <label class="{{ $labelCls }}">{{ $cf['address_line_1']['label'] }} <span class="text-red-500">*</span></label>
                        <input type="text" :name="same ? '' : 'billing[address_line_1]'" :required="!same" value="{{ $billingDefault['address_line_1'] ?? '' }}" placeholder="Street address" class="{{ $field }}">
                    </div>
                    @if($cf['address_line_2']['enabled'])
                        <div class="sm:col-span-2">
                            <label class="{{ $labelCls }}">{{ $cf['address_line_2']['label'] }} <span class="text-gray-400">(optional)</span></label>
                            <input type="text" :name="same ? '' : 'billing[address_line_2]'" value="{{ $billingDefault['address_line_2'] ?? '' }}" placeholder="{{ $cf['address_line_2']['label'] }}" class="{{ $field }}">
                        </div>
                    @endif
                    <div>
                        <label class="{{ $labelCls }}">{{ $cf['city']['label'] }}</label>
                        <input type="text" :name="same ? '' : 'billing[city]'" :required="!same && {{ $req('city') ? 'true' : 'false' }}" value="{{ $billingDefault['city'] ?? '' }}" placeholder="{{ $cf['city']['label'] }}" class="{{ $field }}">
                    </div>
                    @if($cf['state']['enabled'])
                        <div>
                            <label class="{{ $labelCls }}">{{ $cf['state']['label'] }}</label>
                            <input type="text" :name="same ? '' : 'billing[state]'" :required="!same && {{ $req('state') ? 'true' : 'false' }}" value="{{ $billingDefault['state'] ?? '' }}" placeholder="{{ $cf['state']['label'] }}" class="{{ $field }}">
                        </div>
                    @endif
                    @if($cf['postal_code']['enabled'])
                        <div>
                            <label class="{{ $labelCls }}">{{ $cf['postal_code']['label'] }}</label>
                            <input type="text" :name="same ? '' : 'billing[postal_code]'" :required="!same && {{ $req('postal_code') ? 'true' : 'false' }}" value="{{ $billingDefault['postal_code'] ?? '' }}" placeholder="{{ $cf['postal_code']['label'] }}" class="{{ $field }}">
                        </div>
                    @endif
                    @php
                        $billingCountry = array_key_exists($billingDefault['country'] ?? '', $allowedCountries)
                            ? $billingDefault['country']
                            : array_key_first($allowedCountries);
                    @endphp
                    <div>
                        <label class="{{ $labelCls }}">{{ $cf['country']['label'] }}</label>
                        @if(count($allowedCountries) === 1)
                            <input type="hidden" :name="same ? '' : 'billing[country]'" value="{{ $billingCountry }}">
                            <div class="flex items-center rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                {{ $allowedCountries[$billingCountry] }}
                            </div>
                        @else
                            <select :name="same ? '' : 'billing[country]'" class="{{ $field }}">
                                @foreach($allowedCountries as $code => $countryName)
                                    <option value="{{ $code }}" @selected($billingCountry === $code)>{{ $countryName }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </div>
            </section>

            {{-- Shipping method --}}
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">4</span>
                    <h2 class="text-base font-semibold text-gray-900">Shipping method</h2>
                </div>
                <div class="mt-4 space-y-2.5" x-show="shippingOptions.length">
                    <template x-for="option in shippingOptions" :key="option.id">
                        <label class="flex cursor-pointer items-center justify-between rounded-lg border p-3.5 text-sm transition"
                               :class="selectedShipping === option.id ? 'border-indigo-600 bg-indigo-50 ring-1 ring-indigo-600' : 'border-gray-300 hover:border-gray-400'">
                            <span class="flex items-center gap-2.5">
                                <input type="radio" :value="option.id" x-model.number="selectedShipping" @change="refreshShipping" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>
                                    <span x-text="option.title" class="font-medium text-gray-900"></span>
                                    <span class="block text-xs text-gray-500" x-show="option.delivery_estimate" x-text="option.delivery_estimate"></span>
                                </span>
                            </span>
                            <span class="font-semibold text-gray-900" x-text="option.cost > 0 ? formatMoney(option.cost) : 'Free'"></span>
                        </label>
                    </template>
                </div>
                <p class="mt-4 flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-2.5 text-sm text-gray-500" x-show="!shippingOptions.length">
                    <svg class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Enter your address above to see shipping options.
                </p>
            </section>

            {{-- Payment --}}
            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">5</span>
                    <h2 class="text-base font-semibold text-gray-900">Payment</h2>
                </div>
                <div class="mt-4 space-y-2.5">
                    @foreach($gateways as $gateway)
                        <label class="block cursor-pointer rounded-lg border border-gray-300 p-3.5 text-sm transition hover:border-gray-400 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50 has-[:checked]:ring-1 has-[:checked]:ring-indigo-600">
                            <span class="flex items-start gap-3">
                                <input type="radio" name="payment_method" value="{{ $gateway->key() }}" required
                                       x-model="payment" @change="refreshShipping()" @checked($loop->first) class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span>
                                    <span class="font-medium text-gray-900">{{ $gateway->title() }}</span>
                                    @if($gateway->description())
                                        <span class="block text-xs text-gray-500">{{ $gateway->description() }}</span>
                                    @endif
                                </span>
                            </span>
                            @if($gateway->instructions())
                                <span x-show="payment === '{{ $gateway->key() }}'" x-cloak
                                      class="mt-2.5 block rounded-md bg-white px-3 py-2 text-xs text-indigo-800 ring-1 ring-indigo-100">
                                    {{ $gateway->instructions() }}
                                </span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </section>

            {{-- Order note --}}
            @if($msg['note_enabled'])
                <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                    <label for="order-note" class="text-sm font-semibold text-gray-900">{{ $msg['note_label'] }} <span class="font-normal text-gray-400">(optional)</span></label>
                    <textarea id="order-note" name="note" rows="2" class="mt-2 {{ $field }}" placeholder="Notes about your order, delivery instructions…">{{ old('note') }}</textarea>
                </section>
            @endif
        </div>

        {{-- Order summary --}}
        <aside class="lg:col-span-2">
            <div class="sticky top-20 rounded-xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-semibold text-gray-900">Your order</h2>
                <ul class="mt-4 divide-y divide-gray-100 text-sm">
                    @foreach($cart->items as $item)
                        <li class="flex items-center gap-3 py-3"
                            x-data="{
                                qty: {{ $item->qty }},
                                unit: {{ $item->unitPrice() }},
                                busy: false,
                                async set(next) {
                                    next = Math.max(1, Math.min(999, Math.round(next || 1)));
                                    if (this.busy || next === this.qty) return;
                                    this.busy = true;
                                    const prev = this.qty; this.qty = next;
                                    try {
                                        await shopkit.setQty({{ $item->id }}, next);
                                        this.$dispatch('cart-line-changed');
                                    } catch (e) { this.qty = prev; alert(e.message); }
                                    finally { this.busy = false; }
                                },
                                async drop() {
                                    if (this.busy) return;
                                    this.busy = true;
                                    try {
                                        const data = await shopkit.removeItem({{ $item->id }});
                                        if (data.empty) { window.location = '{{ route('cart.index') }}'; return; }
                                        this.$dispatch('cart-line-changed');
                                        this.$el.remove();
                                    } catch (e) { alert(e.message); this.busy = false; }
                                }
                            }"
                            data-line="{{ $item->id }}"
                            :class="busy && 'opacity-50'">
                            <div class="relative h-14 w-14 shrink-0 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                @if($img = $item->product?->featuredImageWebpUrl())
                                    <img src="{{ $img }}" alt="{{ $item->displayName() }}" loading="lazy" width="56" height="56" class="h-full w-full object-cover">
                                @endif
                                <span class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-gray-700 px-1 text-[11px] font-semibold text-white" x-text="qty">{{ $item->qty }}</span>
                            </div>
                            <div class="flex flex-1 flex-col gap-1.5">
                                <span class="text-gray-700">{{ $item->displayName() }}</span>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center rounded-md border border-gray-300">
                                        <button type="button" class="flex h-7 w-7 items-center justify-center text-gray-600 hover:bg-gray-100 disabled:opacity-40" aria-label="Decrease quantity" :disabled="busy || qty <= 1" @click="set(qty - 1)">&minus;</button>
                                        <span class="w-8 text-center text-sm tabular-nums" x-text="qty"></span>
                                        <button type="button" class="flex h-7 w-7 items-center justify-center text-gray-600 hover:bg-gray-100 disabled:opacity-40" aria-label="Increase quantity" :disabled="busy" @click="set(qty + 1)">+</button>
                                    </div>
                                    <button type="button" class="text-xs text-gray-400 hover:text-red-600 hover:underline disabled:opacity-40" :disabled="busy" @click="drop()">Remove</button>
                                </div>
                            </div>
                            <span class="font-medium text-gray-900" x-text="formatMoney(qty * unit)">{{ price_format($item->lineTotal()) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-2.5 border-t border-gray-200 pt-4 text-sm text-gray-600">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd class="font-medium text-gray-900" x-text="totals.subtotal ?? '{{ price_format($cart->subtotal()) }}'"></dd></div>
                    @if($discount > 0)
                        <div class="flex justify-between text-green-600"><dt>Discount</dt><dd>−{{ price_format($discount) }}</dd></div>
                    @endif
                    <div class="flex justify-between"><dt>Shipping</dt><dd class="font-medium text-gray-900" x-text="totals.shipping ?? '—'"></dd></div>
                    <div class="flex justify-between" x-show="totals.payment_fee_label" x-cloak>
                        <dt x-text="totals.payment_fee_label"></dt><dd class="font-medium text-gray-900" x-text="totals.payment_fee"></dd>
                    </div>
                    <div class="flex justify-between"><dt>Tax</dt><dd class="font-medium text-gray-900" x-text="totals.tax ?? '—'"></dd></div>
                    <div class="flex justify-between border-t border-gray-200 pt-3 text-lg font-bold text-gray-900"><dt>Total</dt><dd x-text="totals.total ?? '{{ price_format(max(0, $cart->subtotal() - $discount)) }}'"></dd></div>
                </dl>
                <button class="mt-5 flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-500/40 disabled:opacity-50" :disabled="submitting">
                    <span x-show="!submitting" class="flex items-center gap-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Place order
                    </span>
                    <span x-show="submitting" style="display:none" class="flex items-center gap-2">
                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        Processing…
                    </span>
                </button>
                <div class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-center text-xs text-gray-500">
                    @if($msg['security_text'] !== '')
                        <p class="flex items-center justify-center gap-1.5">
                            <svg class="h-3.5 w-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ $msg['security_text'] }}
                        </p>
                    @endif
                    <p>Taxes and shipping calculated at checkout.</p>
                </div>
            </div>
        </aside>
    </form>
</div>
</div>

<script>
function checkoutForm() {
    return {
        address: { country: '{{ array_key_exists(old('shipping.country', $defaultShipping?->country ?? ''), store_countries()) ? old('shipping.country', $defaultShipping?->country) : array_key_first(store_countries()) }}', state: '', city: '', postal_code: '' },
        shippingOptions: [],
        selectedShipping: null,
        payment: '{{ $gateways[0]->key() ?? '' }}',
        totals: {},
        submitting: false,
        idempotencyKey: crypto.randomUUID(),
        formatMoney(amount) {
            const n = Number(amount).toFixed({{ store_currency_decimals() }});
            return {!! setting('general.currency_position', 'left') === 'right'
                ? "n + ' ' + ".json_encode(store_currency_symbol())
                : json_encode(store_currency_symbol())." + n" !!};
        },
        lock(event) {
            if (this.submitting) { event.preventDefault(); return; }
            // Mirror shipping fields into billing when "same as shipping" is on.
            const form = event.target;
            if (form.querySelector('input[name="billing_same"]')) {
                form.querySelectorAll('[name^="shipping["]').forEach((input) => {
                    const billingName = input.name.replace('shipping[', 'billing[');
                    const clone = document.createElement('input');
                    clone.type = 'hidden'; clone.name = billingName; clone.value = input.value;
                    form.appendChild(clone);
                });
            }
            this.submitting = true;
        },
        identify(event) {
            const email = (event?.target?.value || '').trim();
            if (!email || !email.includes('@')) return;
            const name = [document.querySelector('[name="shipping[first_name]"]')?.value, document.querySelector('[name="shipping[last_name]"]')?.value].filter(Boolean).join(' ');
            const phone = document.querySelector('[name="phone"]')?.value || '';
            fetch('{{ route('cart.identify') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ email, name, phone }),
                keepalive: true,
            }).catch(() => {});
        },
        async refreshShipping() {
            if (!this.address.country) return;
            try {
                const response = await fetch('{{ route('checkout.shipping-options') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ ...this.address, shipping_method_id: this.selectedShipping, payment_method: this.payment }),
                });
                if (!response.ok) return;
                const data = await response.json();
                this.shippingOptions = data.options;
                this.selectedShipping = data.selected;
                this.totals = data.totals;
            } catch (e) { /* keep server-side totals as source of truth */ }
        },
        init() { this.refreshShipping(); },
    };
}
</script>
@endsection
