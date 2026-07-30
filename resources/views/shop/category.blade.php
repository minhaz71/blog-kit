@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <x-breadcrumbs :crumbs="$seo->breadcrumbs" />

    @if($category->banner)
        <img src="{{ asset('storage/'.$category->banner) }}" alt="{{ $category->image_alt ?: $category->name }}" class="mt-4 h-40 w-full rounded-xl object-cover sm:h-56" width="1200" height="224" loading="eager" fetchpriority="high">
    @endif

    <h1 class="mt-4 text-3xl font-bold">{{ $category->name }}</h1>

    @if($category->description)
        <div class="prose prose-sm mt-2 max-w-3xl text-gray-600">{!! $category->description !!}</div>
    @endif

    @if($category->children->isNotEmpty())
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach($category->children as $child)
                <a href="{{ $child->url() }}" class="rounded-full border border-gray-300 px-4 py-1.5 text-sm hover:border-indigo-600 hover:text-indigo-600">{{ $child->name }}</a>
            @endforeach
        </div>
    @endif

    <div class="mt-6 flex flex-col gap-8 lg:flex-row">
        @include('shop.partials.filters')
        <div class="flex-1">
            @include('shop.partials.product-grid')
        </div>
    </div>

    {{-- SEO content block --}}
    @if($category->content_block)
        <section class="prose prose-sm mt-12 max-w-3xl">{!! $category->content_block !!}</section>
    @endif

    <x-faq-section :faqs="$category->faqs" />
</div>
@include('partials.custom-code', ['model' => $category])
@endsection
