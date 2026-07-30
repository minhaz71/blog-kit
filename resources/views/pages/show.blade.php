@extends('layouts.app')

@php
    // Legal / policy pages get a "Last updated" line; marketing pages (contact,
    // about) don't need one.
    $legal = in_array($page->slug, ['terms-and-conditions', 'privacy-policy', 'refund-policy', 'shipping-policy'], true);
    // City delivery pages lead with the category catalogue so a local visitor
    // lands straight on the product categories, then reads the copy below.
    $isCity = str_starts_with($page->slug, 'terea-delivery-');
    // Contact page always shows the contact cards + message form, even if the
    // admin never added the {{contact_info}} / {{contact_form}} shortcodes to
    // the page body. Guarded so we never render them twice.
    $isContact = in_array($page->slug, ['contact-us', 'contact'], true);
    $bodyHasForm = str_contains((string) $page->content, 'contact_form');
    $bodyHasInfo = str_contains((string) $page->content, 'contact_info');
@endphp

@section('content')
@if (!empty($isPreview))
    <div class="bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-white">
        Draft preview — not visible to visitors.
    </div>
@endif

@if($isCity)
    @php
        // "TEREA Delivery in Abu Dhabi" → "Abu Dhabi" for the lead line.
        $cityName = \Illuminate\Support\Str::of($page->title)->after(' in ')->trim()->value();
    @endphp
    {{-- City delivery pages: breadcrumb + title + catalogue all share ONE
         aligned column, and the categories lead (before the copy) so a local
         visitor jumps straight to a category page. --}}
    <div class="mx-auto max-w-7xl px-4 pt-8 pb-10 sm:px-6">
        <x-breadcrumbs :crumbs="$seo->breadcrumbs" />
        <header class="mb-8 mt-4">
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">{{ $page->title }}</h1>
            <p class="mt-3 max-w-2xl text-base text-gray-600">
                Genuine IQOS TEREA{{ $cityName ? ', delivered across '.$cityName : '' }} — browse a category below and order in a couple of taps.
            </p>
        </header>
        @include('partials.city-catalogue', ['flush' => true, 'hideHead' => true])
    </div>

    <div class="mx-auto max-w-3xl px-4 pb-10 sm:px-6">
        <div class="pd-content">{!! parse_shortcodes($page->content) !!}</div>
        <x-faq-section :faqs="$page->faqs" />
    </div>
@else
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <x-breadcrumbs :crumbs="$seo->breadcrumbs" />

        {{-- Branded page header --}}
        <header class="mt-5 border-b border-gray-200 pb-6">
            <h1 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">{{ $page->title }}</h1>
            @if($legal)
                <p class="mt-2 flex items-center gap-1.5 text-sm text-gray-500">
                    <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                    Last updated {{ $page->updated_at->format('F j, Y') }}
                </p>
            @endif
        </header>

        {{-- Brand-styled content (same .pd-content system as product descriptions:
             teal accent headings, branded links, rounded tables). --}}
        <div class="pd-content mt-8">{!! parse_shortcodes($page->content) !!}</div>

        @if($isContact)
            @unless($bodyHasInfo) @include('partials.contact-info') @endunless
            @unless($bodyHasForm) @include('partials.contact-form') @endunless
        @endif

        <x-faq-section :faqs="$page->faqs" />
    </div>
@endif

@include('partials.custom-code', ['model' => $page])
@endsection
