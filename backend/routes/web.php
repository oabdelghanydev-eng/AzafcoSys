<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Add named login route for Sanctum - returns JSON 401 for API requests
Route::get('/login', function () {
    return response()->json([
        'success' => false,
        'error' => [
            'code' => 'AUTH_001',
            'message' => 'Unauthenticated. Please login first.',
        ],
    ], 401);
})->name('login');
