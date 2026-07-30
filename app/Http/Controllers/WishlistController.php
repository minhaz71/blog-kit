<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return view('account.wishlist', [
            'products' => $request->user()->wishlist()->with(['images', 'brand'])->paginate(12),
        ]);
    }

    public function toggle(Request $request, Product $product)
    {
        $result = $request->user()->wishlist()->toggle($product->id);
        $added = count($result['attached']) > 0;

        if ($request->expectsJson()) {
            return response()->json(['added' => $added]);
        }

        return back()->with('success', $added ? 'Added to wishlist.' : 'Removed from wishlist.');
    }
}
