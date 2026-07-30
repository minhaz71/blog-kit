<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Populated with product/category/cart/order endpoints (task: API system).
    Route::get('/ping', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));
});
