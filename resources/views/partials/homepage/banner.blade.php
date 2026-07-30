@php
    $image = $section->setting('image');
    $link = $section->setting('link_url');
    $dims = image_dimensions($image); // reserve the box before load — no CLS
@endphp
@if($image)
    <section class="mt-4">
        @if($link)<a href="{{ $link }}" class="group block">@endif
            <div class="relative overflow-hidden rounded-lg border border-gray-200 shadow-sm">
                <img src="{{ asset('storage/'.$image) }}" alt="{{ $section->title ?? '' }}" loading="lazy"
                     @if($dims) width="{{ $dims[0] }}" height="{{ $dims[1] }}" @endif
                     class="h-auto w-full transition duration-300 group-hover:scale-[1.02]">
                @if($section->title || $section->subtitle)
                    <div class="absolute inset-0 flex flex-col justify-center bg-gradient-to-r from-black/60 via-black/20 to-transparent p-6 text-white sm:p-12">
                        @if($section->title)<h2 class="text-2xl font-extrabold sm:text-4xl">{{ $section->title }}</h2>@endif
                        @if($section->subtitle)<p class="mt-2 max-w-xl text-sm text-white/90 sm:text-base">{{ $section->subtitle }}</p>@endif
                        <span class="mt-4 inline-flex w-fit items-center gap-1 rounded-full bg-white px-4 py-1.5 text-sm font-bold text-gray-900">Shop now →</span>
                    </div>
                @endif
            </div>
        @if($link)</a>@endif
    </section>
@endif
