<?php

use Illuminate\Support\Facades\Route;

// REST API routes are registered in this file. Populated by ApiControllers below.
require __DIR__.'/api-v1.php';

Route::fallback(fn () => response()->json(['message' => 'Not found.'], 404));
