<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Seo\SeoManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, SeoManager $seo)
    {
        $isPublic = $product->status === 'published' && $product->visibility !== 'hidden';
        $isPreview = ! $isPublic && ($request->user()?->can('manage products') ?? false);

        abort_unless($isPublic || $isPreview, 404);

        $product->load([
            'brand', 'images', 'categories', 'tags', 'faqs', 'seoMeta', 'shippingClass',
            'attributes.values', 'attributeValues.attribute',
            'activeVariations.attributeValues.attribute',
            'relatedProducts' => fn ($q) => $q->visible()->with('images')->take(8),
            'upsells' => fn ($q) => $q->visible()->with('images')->take(4),
            'groupedChildren' => fn ($q) => $q->visible()->with('images'),
        ]);

        $reviews = $product->approvedReviews()->latest()->paginate(10, ['*'], 'reviews_page');

        // If no explicit related products, fall back to same-category picks.
        $related = $product->relatedProducts;

        if ($related->isEmpty() && $product->categories->isNotEmpty()) {
            $related = Product::visible()
                ->whereKeyNot($product->id)
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $product->categories->pluck('id')))
                ->with('images')
                ->inRandomOrder()
                ->take(8)
                ->get();
        }

        if (! $isPreview) {
            $product->increment('views_count');
            $this->trackRecentlyViewed($request, $product);
        }

        return view('shop.product', [
            'product' => $product,
            'reviews' => $reviews,
            'related' => $related,
            'seo' => $seo->forProduct($product),
            'isPreview' => $isPreview,
        ]);
    }

    protected function trackRecentlyViewed(Request $request, Product $product): void
    {
        try {
            DB::table('recently_viewed_products')->updateOrInsert(
                [
                    'user_id' => auth()->id(),
                    'session_id' => auth()->check() ? null : $request->session()->getId(),
                    'product_id' => $product->id,
                ],
                ['viewed_at' => now()],
            );
        } catch (\Throwable) {
            // Non-critical tracking; never break the product page.
        }
    }
}
