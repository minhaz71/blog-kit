<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Populated with product/category/cart/order endpoints (task: API system).
    Route::get('/ping', fn () => response()->json(['ok' => true, 'time' => now()->toIso8601String()]));

    // ── Multisite network API (spoke side) ─────────────────────────────
    // Every call is HMAC-verified by the `network.signed` middleware. These
    // routes stay registered even when the module is off (the middleware
    // returns 404 then), so URL generation never breaks.
    Route::prefix('network')->middleware('network.signed')->group(function () {
        Route::get('/ping', [\App\Http\Controllers\Api\NetworkController::class, 'ping']);
        Route::get('/capabilities', [\App\Http\Controllers\Api\NetworkController::class, 'capabilities']);
        // Phase 2: accept a post pushed from a hub (idempotent upsert).
        Route::post('/posts', [\App\Http\Controllers\Api\NetworkController::class, 'storePost']);
        // Phase 4: list this site's posts for a hub to mirror.
        Route::get('/posts', [\App\Http\Controllers\Api\NetworkController::class, 'listPosts']);
        // Internal-link catalog: this site's linkable pages (posts, categories,
        // products, home) with real URLs + funnel identity, so a hub writes
        // articles that link to THIS site's own content.
        Route::get('/link-catalog', [\App\Http\Controllers\Api\NetworkController::class, 'linkCatalog']);
        // Phase 5: delete a post this hub manages ({id} = the hub's network_post_id).
        Route::delete('/posts/{id}', [\App\Http\Controllers\Api\NetworkController::class, 'deletePost'])
            ->where('id', '[0-9]+');
        // One-click update: trigger this site's own blogkit:update in the background.
        Route::post('/update', [\App\Http\Controllers\Api\NetworkController::class, 'update']);
    });
});
