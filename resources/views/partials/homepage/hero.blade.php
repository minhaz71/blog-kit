@php
    // The hero design is driven by the active Home page design (Admin →
    // Appearance → Home page design). Content comes from the hero section.
    $variant = \App\Support\HomeStyle::tokens()['hero'] ?? 'gradient';
    $img = $section->setting('image');
    $mobileImg = $section->setting('mobile_image');
    $opacity = (int) $section->setting('overlay_opacity', 45);
    $badge = $section->setting('badge');
    $title = $section->title ?? setting('seo.homepage_title', config('app.name'));
    $subtitle = $section->subtitle ?? $section->setting('description');
    $btn1 = $section->setting('button_text');
    $btn1Url = $section->setting('button_url');
    $btn2 = $section->setting('button2_text');
    $btn2Url = $section->setting('button2_url');
    // If the admin chose an image-led design but set no image, fall back to gradient.
    if ($variant === 'image' && ! $img) {
        $variant = 'gradient';
    }
@endphp

@php
    // Reusable badge chip (brand-glass on dark heroes, brand-tint on light).
    $badgeChip = fn ($light = true) => $badge
        ? '<span class="inline-flex items-center gap-1.5 rounded-full '.($light ? 'bg-white/15 text-white ring-white/25' : 'bg-brand-tint text-brand ring-brand/20').' px-3 py-1 text-xs font-semibold uppercase tracking-wide ring-1 backdrop-blur-sm">'.e($badge).'</span>'
        : '';
@endphp

{{-- The two CTA buttons, styled per light/dark background. --}}
@php
    $ctas = function ($onBrand = true) use ($btn1, $btn1Url, $btn2, $btn2Url) {
        if (! (($btn1 && $btn1Url) || ($btn2 && $btn2Url))) return '';
        $out = '<div class="mt-8 flex flex-wrap items-center gap-3">';
        if ($btn1 && $btn1Url) {
            $out .= $onBrand
                ? '<a href="'.e($btn1Url).'" class="inline-flex items-center gap-2 rounded-full bg-white px-6 py-3 text-sm font-semibold text-brand shadow-sm transition hover:bg-brand-tint">'.e($btn1).' <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></a>'
                : '<a href="'.e($btn1Url).'" class="bg-brand text-brand-fg hover:bg-brand-dark inline-flex items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold shadow-sm transition">'.e($btn1).' <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg></a>';
        }
        if ($btn2 && $btn2Url) {
            $out .= $onBrand
                ? '<a href="'.e($btn2Url).'" class="inline-flex items-center rounded-full px-6 py-3 text-sm font-semibold text-white ring-1 ring-white/40 transition hover:bg-white/10">'.e($btn2).'</a>'
                : '<a href="'.e($btn2Url).'" class="text-brand ring-brand/40 hover:bg-brand-tint inline-flex items-center rounded-full px-6 py-3 text-sm font-semibold ring-1 transition">'.e($btn2).'</a>';
        }
        return $out.'</div>';
    };
@endphp

@switch($variant)

{{-- 1. Minimal — plain text hero on the page background, no panel. --}}
@case('minimal')
    <section class="mt-8 border-b border-gray-200 pb-12">
        {!! $badgeChip(false) !!}
        <h1 class="mt-4 max-w-3xl text-4xl font-extrabold tracking-tight text-balance text-gray-900 sm:text-6xl">{{ $title }}</h1>
        @if($subtitle)<p class="mt-5 max-w-2xl text-lg leading-relaxed text-gray-600">{{ $subtitle }}</p>@endif
        {!! $ctas(false) !!}
    </section>
    @break

{{-- 2. Classic — masthead: brand top rule, centered-ish, thin bottom rule. --}}
@case('classic')
    <section class="mt-8 border-t-4 border-brand pt-8 text-center">
        {!! $badgeChip(false) !!}
        <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-balance text-gray-900 sm:text-5xl">{{ $title }}</h1>
        @if($subtitle)<p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600">{{ $subtitle }}</p>@endif
        <div class="flex justify-center">{!! $ctas(false) !!}</div>
        <div class="mt-10 border-b border-gray-200"></div>
    </section>
    @break

{{-- 3. Bold — full-bleed brand band, oversized display title. --}}
@case('bold')
    <section class="grad-brand relative left-1/2 right-1/2 -mx-[50vw] mt-6 w-screen overflow-hidden text-white">
        <div class="absolute inset-0 opacity-[0.12]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 24px 24px;"></div>
        <div class="relative mx-auto max-w-5xl px-6 py-24 text-center sm:py-32">
            {!! $badgeChip(true) !!}
            <h1 class="mt-5 text-5xl font-extrabold tracking-tight text-balance sm:text-7xl">{{ $title }}</h1>
            @if($subtitle)<p class="mx-auto mt-6 max-w-2xl text-xl text-white/85">{{ $subtitle }}</p>@endif
            <div class="flex justify-center">{!! $ctas(true) !!}</div>
        </div>
    </section>
    @break

{{-- 4. Image — full cover photo with the title overlaid. --}}
@case('image')
    <section class="relative mt-6 overflow-hidden rounded-3xl bg-gray-900 text-white">
        <picture>
            @if($mobileImg)<source media="(max-width: 640px)" srcset="{{ asset('storage/'.$mobileImg) }}">@endif
            <img src="{{ asset('storage/'.$img) }}" alt="" loading="eager" fetchpriority="high" class="absolute inset-0 h-full w-full object-cover">
        </picture>
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/40 to-black/20" style="opacity: {{ max(30, min(100, $opacity)) / 100 }}"></div>
        <div class="relative px-6 py-24 sm:px-12 sm:py-32">
            <div class="max-w-2xl">
                {!! $badgeChip(true) !!}
                <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-balance sm:text-6xl">{{ $title }}</h1>
                @if($subtitle)<p class="mt-5 max-w-xl text-lg text-white/90">{{ $subtitle }}</p>@endif
                {!! $ctas(true) !!}
            </div>
        </div>
    </section>
    @break

{{-- 5. Split — title on white beside a brand/image panel. --}}
@case('split')
    <section class="mt-6 grid items-stretch gap-6 lg:grid-cols-2">
        <div class="flex flex-col justify-center py-6">
            {!! $badgeChip(false) !!}
            <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-balance text-gray-900 sm:text-5xl">{{ $title }}</h1>
            @if($subtitle)<p class="mt-5 max-w-lg text-lg text-gray-600">{{ $subtitle }}</p>@endif
            {!! $ctas(false) !!}
        </div>
        <div class="grad-brand relative min-h-[16rem] overflow-hidden rounded-3xl">
            @if($img)<img src="{{ asset('storage/'.$img) }}" alt="" class="absolute inset-0 h-full w-full object-cover mix-blend-overlay opacity-90">@endif
            <div class="absolute inset-0 opacity-[0.15]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>
        </div>
    </section>
    @break

{{-- 6. Banner — short wide brand masthead. --}}
@case('banner')
    <section class="grad-brand relative mt-6 overflow-hidden rounded-2xl px-6 py-10 text-white sm:px-10">
        <div class="relative flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                {!! $badgeChip(true) !!}
                <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ $title }}</h1>
                @if($subtitle)<p class="mt-2 max-w-xl text-white/85">{{ $subtitle }}</p>@endif
            </div>
            <div class="shrink-0">{!! $ctas(true) !!}</div>
        </div>
    </section>
    @break

{{-- 7. Side — content on a brand panel with a decorative shape column. --}}
@case('side')
    <section class="grad-brand relative mt-6 overflow-hidden rounded-3xl text-white">
        <div class="absolute right-0 top-0 hidden h-full w-1/3 opacity-30 lg:block" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 20px 20px;"></div>
        <div class="absolute -bottom-24 -right-16 h-80 w-80 rounded-full bg-white/10 blur-3xl"></div>
        <div class="relative max-w-2xl px-6 py-20 sm:px-12 sm:py-24">
            {!! $badgeChip(true) !!}
            <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-balance sm:text-5xl">{{ $title }}</h1>
            @if($subtitle)<p class="mt-5 max-w-xl text-lg text-white/85">{{ $subtitle }}</p>@endif
            {!! $ctas(true) !!}
        </div>
    </section>
    @break

{{-- 8. Boxed — hero inside a soft white card with a brand accent bar. --}}
@case('boxed')
    <section class="mt-6 overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-sm">
        <div class="grad-brand h-1.5 w-full"></div>
        <div class="px-6 py-16 sm:px-12 sm:py-20">
            {!! $badgeChip(false) !!}
            <h1 class="mt-4 max-w-3xl text-4xl font-extrabold tracking-tight text-balance text-gray-900 sm:text-5xl">{{ $title }}</h1>
            @if($subtitle)<p class="mt-5 max-w-2xl text-lg text-gray-600">{{ $subtitle }}</p>@endif
            {!! $ctas(false) !!}
        </div>
    </section>
    @break

{{-- 9. Centered — brand gradient panel, centered composition. --}}
@case('centered')
    <section class="grad-brand relative mt-6 overflow-hidden rounded-3xl text-white">
        <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>
        <div class="relative mx-auto max-w-3xl px-6 py-24 text-center sm:py-28">
            <div class="flex justify-center">{!! $badgeChip(true) !!}</div>
            <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-balance sm:text-6xl">{{ $title }}</h1>
            @if($subtitle)<p class="mx-auto mt-5 max-w-xl text-lg text-white/85">{{ $subtitle }}</p>@endif
            <div class="flex justify-center">{!! $ctas(true) !!}</div>
        </div>
    </section>
    @break

{{-- 10. Gradient (default) — rounded brand panel, dot grid, left-aligned. --}}
@default
    <section class="grad-brand relative mt-6 overflow-hidden rounded-3xl text-white">
        <div class="absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.14]" style="background-image: radial-gradient(circle at 1px 1px, #fff 1px, transparent 0); background-size: 22px 22px;"></div>
        <div class="relative px-6 py-20 sm:px-12 sm:py-28 lg:py-32">
            <div class="max-w-3xl">
                {!! $badgeChip(true) !!}
                <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ $title }}</h1>
                @if($subtitle)<p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/85 sm:text-xl">{{ $subtitle }}</p>@endif
                {!! $ctas(true) !!}
            </div>
        </div>
    </section>
@endswitch
