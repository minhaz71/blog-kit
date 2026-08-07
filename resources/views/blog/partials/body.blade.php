{{-- Comparison articles (ecommerce module, retained): the products under
     review. Empty for ordinary blog posts, so it renders nothing there. --}}
@if(($compared = $post->comparedProducts())->isNotEmpty())
    <section class="mt-8" aria-labelledby="compared-products-heading">
        <h2 id="compared-products-heading" class="text-lg font-bold">Compared in this article</h2>
        <div class="mt-3 grid grid-cols-2 gap-4">
            @foreach($compared as $comparedProduct)
                <x-product-card :product="$comparedProduct" />
            @endforeach
        </div>
    </section>
@endif

{{-- bd-article: the tag-based blog design layer (blog.css) — every semantic
     tag is styled, no classes needed in the content itself. Heading ids are
     injected for the table of contents / deep links. --}}
<div class="prose bd-article mt-8 max-w-none {{ $bodyClass ?? '' }}">
    {!! preg_replace_callback('/<h([23])([^>]*)>(.*?)<\/h\1>/i', fn ($m) => sprintf('<h%s%s id="%s">%s</h%s>', $m[1], $m[2], str(strip_tags($m[3]))->slug(), $m[3], $m[1]), $post->content ?? '') !!}
</div>
