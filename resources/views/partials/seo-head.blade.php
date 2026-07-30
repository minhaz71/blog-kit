@php
    /** @var \App\Services\Seo\SeoData|null $seo */
    // Pages without their own SEO payload fall back here. Cart, checkout,
    // account, wishlist and auth pages must NEVER be indexed — matched by
    // route name so no utility page can slip through.
    if (! isset($seo)) {
        $routeName = (string) request()->route()?->getName();
        $isUtility = (bool) preg_match('/^(cart|checkout|account|wishlist|login|register|password|verification|two-factor)\b/', $routeName);
        $seo = app(\App\Services\Seo\SeoManager::class)->forUtility(config('app.name'), noindex: $isUtility);
    }
@endphp
<title>{{ $seo->fullTitle() }}</title>
@if($seo->description)
    <meta name="description" content="{{ $seo->description }}">
@endif
{{-- "Discourage search engines" (Admin → SEO settings) forces noindex on every
     page while on; otherwise the explicit default lets Google show large image
     previews (better CTR). --}}
@if(setting('seo.discourage_indexing'))
<meta name="robots" content="noindex, nofollow">
@else
<meta name="robots" content="{{ $seo->robots ?: 'index, follow, max-image-preview:large' }}">
@endif

@if($seo->canonical)
    <link rel="canonical" href="{{ $seo->canonical }}">
@endif

@if($favicon = \App\Support\StoreBranding::faviconUrl())
    <link rel="icon" href="{{ $favicon }}">
@endif

{{-- Markdown-for-agents: point AI crawlers at the clean .md representation. --}}
@if(setting('seo.markdown_for_agents', true) && request()->routeIs('product.show', 'category.show', 'blog.show', 'page.show', 'home'))
    <link rel="alternate" type="text/markdown" href="{{ request()->routeIs('home') ? url('/index.md') : url(request()->path()).'.md' }}">
@endif

{{-- Search-engine ownership verification (Admin → SEO settings → Search engine
     verification). Rendered site-wide, and even while "discourage indexing" is
     on, so ownership can be verified from a staging site. Accepts a bare token
     or a full <meta> tag pasted from the console (content is extracted). --}}
@php
    $verifyToken = function (string $key): string {
        $raw = trim((string) setting("seo.{$key}"));
        if ($raw === '') { return ''; }
        return preg_match('/content=["\']([^"\']+)["\']/i', $raw, $m) ? $m[1] : $raw;
    };
@endphp
@if($v = $verifyToken('verify_google'))<meta name="google-site-verification" content="{{ $v }}">@endif
@if($v = $verifyToken('verify_bing'))<meta name="msvalidate.01" content="{{ $v }}">@endif
@if($v = $verifyToken('verify_yandex'))<meta name="yandex-verification" content="{{ $v }}">@endif
@if($v = $verifyToken('verify_baidu'))<meta name="baidu-site-verification" content="{{ $v }}">@endif
@if($v = $verifyToken('verify_pinterest'))<meta name="p:domain_verify" content="{{ $v }}">@endif

{{-- Open Graph — auto-filled from the page's SEO title/description/image.
     Per guidelines: og:title = content title (site name rides og:site_name),
     image dimension hints so the card renders on the FIRST share, alt text
     for accessibility, and per-type article:*/product:* enrichment. --}}
<meta property="og:site_name" content="{{ setting('seo.site_title', config('app.name')) }}">
<meta property="og:locale" content="{{ $seo->ogLocale() }}">
<meta property="og:type" content="{{ $seo->ogType }}">
<meta property="og:title" content="{{ $seo->resolvedOgTitle() }}">
@if($seo->resolvedOgDescription())
    <meta property="og:description" content="{{ $seo->resolvedOgDescription() }}">
@endif
<meta property="og:url" content="{{ $seo->canonical ?? url()->current() }}">
@if($seo->resolvedOgImage())
    <meta property="og:image" content="{{ $seo->resolvedOgImage() }}">
    @if($dimensions = $seo->ogImageDimensions())
        <meta property="og:image:width" content="{{ $dimensions[0] }}">
        <meta property="og:image:height" content="{{ $dimensions[1] }}">
    @endif
    @if($seo->ogImageAlt)
        <meta property="og:image:alt" content="{{ $seo->ogImageAlt }}">
    @endif
@endif
@foreach($seo->ogExtra as $property => $values)
    @foreach((array) $values as $value)
        <meta property="{{ $property }}" content="{{ $value }}">
    @endforeach
@endforeach

{{-- Twitter Card --}}
<meta name="twitter:card" content="{{ $seo->resolvedTwitterImage() ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seo->resolvedTwitterTitle() }}">
@if($seo->resolvedTwitterDescription())
    <meta name="twitter:description" content="{{ $seo->resolvedTwitterDescription() }}">
@endif
@if($seo->resolvedTwitterImage())
    <meta name="twitter:image" content="{{ $seo->resolvedTwitterImage() }}">
    @if($seo->ogImageAlt)
        <meta name="twitter:image:alt" content="{{ $seo->ogImageAlt }}">
    @endif
@endif

{{-- JSON-LD structured data --}}
@if($seo->schemas)
    <script type="application/ld+json">{!! app(\App\Services\Seo\SeoManager::class)->jsonLd($seo) !!}</script>
@endif
