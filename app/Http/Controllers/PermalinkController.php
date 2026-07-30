<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\Permalinks;
use Illuminate\Http\Request;

/**
 * Root-level permalink resolver — the final catch-all `/{slug}` route.
 *
 * By default only CMS pages live at the root, so this behaves exactly like the
 * old page catch-all. When the owner clears a product/category base (root-level
 * URLs), this also resolves those: a single route is used so Laravel's
 * model-binding-failure-hard-404 can't shadow pages. Precedence: root-level
 * product → root-level category → page → 404.
 *
 * Each match is delegated to the real controller so page rendering, SEO and
 * visibility rules stay in one place.
 */
class PermalinkController extends Controller
{
    public function resolve(Request $request, string $slug)
    {
        if (Permalinks::base('product') === ''
            && $product = Product::where('slug', $slug)->first()) {
            return app()->call(ProductController::class.'@show', ['product' => $product]);
        }

        if (Permalinks::base('category') === ''
            && $category = Category::where('slug', $slug)->first()) {
            return app()->call(ShopController::class.'@category', ['category' => $category]);
        }

        $page = Page::where('slug', $slug)->firstOrFail();

        return app()->call(PageController::class.'@show', ['page' => $page]);
    }
}
