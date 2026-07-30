<x-pb-block :data="$block" class="mt-12">
    <section id="product-reviews" aria-labelledby="reviews-heading">
        <h2 id="reviews-heading" class="text-xl font-bold" style="color: var(--pb-heading, inherit)">
            {{ $block['heading'] ?? 'Reviews' }} ({{ $product->reviews_count }})
        </h2>

        @if($reviews->isNotEmpty())
            <div class="mt-4 space-y-4">
                @foreach($reviews as $review)
                    <article class="rounded-lg border border-gray-200 p-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-rating-stars :rating="$review->rating" />
                                <span class="text-sm font-semibold">{{ $review->author_name }}</span>
                                @if($review->is_verified_purchase)
                                    <span class="rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-700">Verified purchase</span>
                                @endif
                            </div>
                            <time class="text-xs text-gray-400" datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('M j, Y') }}</time>
                        </div>
                        @if($review->title)
                            <p class="mt-2 font-medium">{{ $review->title }}</p>
                        @endif
                        <p class="mt-1 text-sm text-gray-600">{{ $review->body }}</p>
                    </article>
                @endforeach
                {{ $reviews->links() }}
            </div>
        @else
            <p class="mt-3 text-sm text-gray-500">No reviews yet. Be the first to review this product.</p>
        @endif

        @if($block['show_form'] ?? true)
            <form action="{{ route('reviews.store', $product) }}" method="POST" class="mt-6 max-w-xl space-y-4 rounded-lg border border-gray-200 p-4">
                @csrf
                <p class="font-semibold">Write a review</p>
                <div>
                    <label class="text-sm font-medium" for="rating">Rating</label>
                    <select id="rating" name="rating" required class="mt-1 w-32 rounded-md border-gray-300 text-sm">
                        @foreach([5, 4, 3, 2, 1] as $stars)
                            <option value="{{ $stars }}">{{ $stars }} star{{ $stars > 1 ? 's' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                @guest
                    <div class="grid gap-4 sm:grid-cols-2">
                        <input type="text" name="author_name" required placeholder="Your name" class="rounded-md border-gray-300 text-sm" aria-label="Your name">
                        <input type="email" name="author_email" required placeholder="Your email" class="rounded-md border-gray-300 text-sm" aria-label="Your email">
                    </div>
                @endguest
                <input type="text" name="title" placeholder="Review title (optional)" class="w-full rounded-md border-gray-300 text-sm" aria-label="Review title">
                <textarea name="body" rows="4" required minlength="10" placeholder="Your review" class="w-full rounded-md border-gray-300 text-sm" aria-label="Your review"></textarea>
                <button class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Submit review</button>
            </form>
        @endif
    </section>
</x-pb-block>
