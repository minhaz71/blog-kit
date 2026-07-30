@extends('layouts.app')

@section('content')
@if (!empty($isPreview))
    <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
        Draft preview — this product is not visible to customers.
        <a href="{{ url('/admin/products/'.$product->id.'/edit') }}" class="underline">Back to editor</a>
    </div>
@endif

@php
    $template = $product->resolvedTemplate();
    $blocks = $template->resolvedBlocks();

    // Shared Alpine state for gallery ↔ variations ↔ price ↔ add-to-cart.
    $variationData = $product->activeVariations->map(fn ($v) => [
        'id' => $v->id,
        'options' => $v->optionMap(),
        'price' => price_format($v->currentPrice()),
        'regular' => $v->isOnSale() ? price_format($v->price) : null,
        'in_stock' => $v->isInStock(),
        'image' => $v->imageUrl(),
        'sku' => $v->sku,
    ])->values();
    $variationAttributes = $product->attributes->filter(fn ($a) => $a->pivot->is_variation);

    // Position the 2-column hero at the first left/right block; full-width
    // blocks before it render on top (breadcrumbs), the rest below.
    $heroIndexes = collect($blocks)->filter(fn ($b) => in_array($b['data']['column'] ?? 'full', ['left', 'right'], true))->keys();
    $firstHero = $heroIndexes->min();
    $lastHero = $heroIndexes->max();
@endphp

<div class="pb-product mx-auto {{ $template->containerClass() }} px-4 py-8 sm:px-6"
     x-data='{
        qty: 1,
        selections: {},
        variations: @json($variationData),
        variation: null,
        message: "",
        requiresVariation: {{ $variationAttributes->isNotEmpty() ? 'true' : 'false' }},
        select(attr, value) {
            this.selections[attr] = value;
            this.variation = this.variations.find(v =>
                Object.entries(v.options).every(([a, val]) => this.selections[a] === val)
            ) ?? null;
        },
        async add() {
            this.message = "";
            try {
                await window.shopkit.addToCart({{ $product->id }}, this.qty, this.variation?.id ?? null);
                this.message = "Added to cart ✓";
            } catch (e) { this.message = e.message; }
        }
     }'>

    {{-- Full-width blocks above the hero (e.g. breadcrumbs) --}}
    @foreach($blocks as $i => $block)
        @if(($block['data']['column'] ?? 'full') === 'full' && ($firstHero === null || $i < $firstHero))
            @includeIf('partials.product.'.$block['type'], ['block' => $block['data'] ?? []])
        @endif
    @endforeach

    {{-- Two-column hero --}}
    @if($firstHero !== null)
        <div class="mt-6 grid gap-10 lg:grid-cols-2">
            <div>
                @foreach($blocks as $block)
                    @if(($block['data']['column'] ?? 'full') === 'left')
                        @includeIf('partials.product.'.$block['type'], ['block' => $block['data'] ?? []])
                    @endif
                @endforeach
            </div>
            <div>
                @foreach($blocks as $block)
                    @if(($block['data']['column'] ?? 'full') === 'right')
                        @includeIf('partials.product.'.$block['type'], ['block' => $block['data'] ?? []])
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    {{-- Description, related products, reviews etc. are below the fold for critical CSS. --}}
    <!--critical-fold-->

    {{-- Full-width blocks below the hero --}}
    @foreach($blocks as $i => $block)
        @if(($block['data']['column'] ?? 'full') === 'full' && $firstHero !== null && $i > $lastHero)
            @includeIf('partials.product.'.$block['type'], ['block' => $block['data'] ?? []])
        @endif
    @endforeach

    {{-- Sticky mobile add-to-cart. Slides up once the user scrolls past the CTA. --}}
    @if($product->type !== 'external' && $product->type !== 'grouped')
        <div
            x-data="{ shown: false }"
            x-init="window.addEventListener('scroll', () => { shown = window.scrollY > 480; }, { passive: true })"
            x-show="shown"
            x-transition.opacity
            x-cloak
            class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur lg:hidden"
            style="padding-bottom: calc(env(safe-area-inset-bottom) + 12px); display:none;"
        >
            <div class="flex items-center gap-2.5">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $product->name }}</p>
                    <p class="text-sm text-gray-600">{{ price_format($product->currentPrice()) }}</p>
                </div>
                {{-- Quantity stepper — binds to the SAME qty as the main form. --}}
                <div class="flex shrink-0 items-center overflow-hidden rounded-full border border-gray-300 bg-white">
                    <button type="button"
                            class="flex h-10 w-10 items-center justify-center text-lg leading-none text-gray-600 active:bg-gray-200"
                            @click="qty = Math.max(1, qty - 1)" aria-label="Decrease quantity">−</button>
                    <input type="number" x-model.number="qty" min="1" aria-label="Quantity"
                           class="h-10 w-10 border-0 p-0 text-center text-sm font-semibold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none focus:ring-0">
                    <button type="button"
                            class="flex h-10 w-10 items-center justify-center text-lg leading-none text-gray-600 active:bg-gray-200"
                            @click="qty++" aria-label="Increase quantity">+</button>
                </div>
                <button type="button" @click="add()"
                        :disabled="requiresVariation && !variation"
                        class="shrink-0 rounded-full bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50">
                    Add to cart
                </button>
            </div>
        </div>
    @endif
</div>
@include('partials.custom-code', ['model' => $product])
@endsection
