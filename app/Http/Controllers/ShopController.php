<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Seo\SeoManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request, SeoManager $seo)
    {
        $products = $this->filteredQuery($request)->paginate(24)->withQueryString();

        return view('shop.index', [
            'products' => $products,
            'category' => null,
            'filterData' => $this->filterData(),
            'seo' => $seo->forUtility('Shop', noindex: $request->query() !== []),
        ]);
    }

    public function category(Request $request, Category $category, SeoManager $seo)
    {
        abort_unless(
            $category->is_active || (request()->user()?->can('manage products') ?? false),
            404,
        );

        $categoryIds = $category->descendantIdsWithSelf();

        $query = $this->filteredQuery($request)
            ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $categoryIds));

        if (! $request->filled('sort') && $category->default_product_sort) {
            $query->reorder();
            $this->applySort($query, $category->default_product_sort);
        }

        $products = $query->paginate(24)->withQueryString();

        return view('shop.category', [
            'category' => $category->load(['children' => fn ($q) => $q->where('is_active', true), 'faqs']),
            'products' => $products,
            'filterData' => $this->filterData(),
            'seo' => $seo->forCategory($category, $products->total(), $products->currentPage(), $products->getCollection()),
        ]);
    }

    public function search(Request $request, SeoManager $seo)
    {
        $term = trim((string) $request->query('q', ''));

        $products = collect();

        if ($term !== '') {
            // Same ranked query as the live dropdown, paginated for the
            // full results page (shared service = one relevance model).
            $products = app(\App\Services\Search\ProductSearch::class)
                ->query(\App\Services\Search\ProductSearch::normalize($term))
                ->with(['images'])
                ->paginate(24)
                ->withQueryString();

            // Submitting the form IS a settled search — log it (deduped).
            app(\App\Services\Search\ProductSearch::class)->log($term, $products->total());
        }

        return view('shop.search', [
            'term' => $term,
            'products' => $products,
            'seo' => $seo->forUtility($term !== '' ? "Search results for \"{$term}\"" : 'Search'),
        ]);
    }

    protected function filteredQuery(Request $request): Builder
    {
        $query = Product::visible()->with(['images', 'brand']);

        if ($request->filled('brand')) {
            $query->whereHas('brand', fn ($q) => $q->whereIn('slug', (array) $request->query('brand')));
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->query('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->query('max_price'));
        }

        if ($request->boolean('in_stock')) {
            $query->inStock();
        }

        if ($request->boolean('on_sale')) {
            $query->onSale();
        }

        if ($request->filled('rating')) {
            $query->where('avg_rating', '>=', min(5, (int) $request->query('rating')));
        }

        // Attribute filters: ?attr[color]=red,blue&attr[size]=xl
        foreach ((array) $request->query('attr', []) as $attributeSlug => $valueList) {
            $values = array_filter(explode(',', (string) $valueList));

            if ($values === []) {
                continue;
            }

            $query->whereHas('attributeValues', function ($q) use ($attributeSlug, $values) {
                $q->whereIn('attribute_values.slug', $values)
                    ->whereHas('attribute', fn ($aq) => $aq->where('slug', $attributeSlug));
            });
        }

        $this->applySort($query, (string) $request->query('sort', 'latest'));

        return $query;
    }

    protected function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('avg_rating'),
            'best_selling' => $query->orderByDesc('sales_count'),
            'name' => $query->orderBy('name'),
            default => $query->latest('published_at')->latest('id'),
        };
    }

    protected function filterData(): array
    {
        // Rehydrate on every request — caching Eloquent Collections into some
        // stores loses class identity across process boundaries and breaks views.
        return [
            'brands' => Brand::active()->has('products')->orderBy('name')->get(['id', 'name', 'slug']),
            'attributes' => Attribute::with('values')->get(),
            'categories' => Category::active()->root()->with('children')->orderBy('sort_order')->get(),
        ];
    }
}
