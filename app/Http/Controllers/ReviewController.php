<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
            'author_name' => [auth()->check() ? 'nullable' : 'required', 'string', 'max:100'],
            'author_email' => [auth()->check() ? 'nullable' : 'required', 'email', 'max:255'],
        ]);

        $user = $request->user();
        $email = $user?->email ?? $data['author_email'];

        // One review per product per customer.
        $exists = $product->reviews()
            ->where(fn ($q) => $user ? $q->where('user_id', $user->id) : $q->where('author_email', $email))
            ->exists();

        if ($exists) {
            return back()->withErrors(['body' => 'You have already reviewed this product.']);
        }

        $verified = $user && $user->orders()
            ->whereIn('status', ['processing', 'completed'])
            ->whereHas('items', fn ($q) => $q->where('product_id', $product->id))
            ->exists();

        $product->reviews()->create([
            'user_id' => $user?->id,
            'author_name' => $user?->name ?? $data['author_name'],
            'author_email' => $email,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'body' => $data['body'],
            'is_approved' => false, // moderated in admin
            'is_verified_purchase' => $verified,
        ]);

        return back()->with('success', 'Thanks! Your review is awaiting moderation.');
    }
}
