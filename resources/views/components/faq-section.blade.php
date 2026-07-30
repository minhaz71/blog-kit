@props(['faqs'])
@if($faqs->isNotEmpty())
    <section class="mt-12" aria-labelledby="faq-heading">
        <h2 id="faq-heading" class="text-xl font-bold">Frequently asked questions</h2>
        <div class="mt-4 divide-y divide-gray-200 rounded-lg border border-gray-200">
            @foreach($faqs as $faq)
                <details class="group p-4">
                    <summary class="flex cursor-pointer items-center justify-between font-medium marker:content-none">
                        {{ $faq->question }}
                        <span class="ml-2 text-gray-400 transition group-open:rotate-45" aria-hidden="true">+</span>
                    </summary>
                    <div class="prose prose-sm mt-2 max-w-none text-gray-600">{!! nl2br(e(strip_tags($faq->answer, '<p><br><a><ul><ol><li><strong><em>'))) !!}</div>
                </details>
            @endforeach
        </div>
    </section>
@endif
