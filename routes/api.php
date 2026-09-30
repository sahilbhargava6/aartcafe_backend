<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AuthController;

// ── One-Time Admin Setup (remove after first use) ──
Route::get('/setup-admin', function () {
    $user = \App\Models\User::firstOrCreate(
        ['email' => 'admin@aartcafe.com'],
        [
            'name' => 'Aartcafe Admin',
            'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
        ]
    );
    return response()->json(['message' => 'Admin user ready', 'email' => $user->email]);
});

// ── Authentication ──
Route::post('/login', [AuthController::class, 'login']);

// ── Public Order Submission (Cart) ──
Route::post('/orders', [OrderController::class, 'store']);

// ── Public Reviews ──
Route::get('/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store']);

// ── Website & Product Analytics ──
Route::get('/analytics/website', [AnalyticsController::class, 'website']);
Route::get('/analytics/products', [AnalyticsController::class, 'products']);

// ── Special Filters & Public Endpoints ──
Route::get('/products/new-discoveries', [ProductController::class, 'newDiscoveries']);
Route::get('/products/wedding-specials', [ProductController::class, 'weddingSpecials']);
Route::get('/products/bestsellers', [ProductController::class, 'bestsellers']);
Route::get('/products/hero-featured', [ProductController::class, 'heroFeatured']);
Route::get('/products/slug/{slug}', [ProductController::class, 'showBySlug']);

// Public Read Resources
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/pages', [PageController::class, 'index']);

// ── File Uploads ──
Route::post('/upload', [UploadController::class, 'store']);

// ── Protected Dashboard Routes (Sanctum) ──
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Orders Management
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);

    // Products Write/Edit
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::post('/products/bulk-import', [ProductController::class, 'bulkImport']);

    // Categories Write/Edit
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // Banners & Pages Write/Edit
    Route::post('/banners', [BannerController::class, 'store']);
    Route::put('/banners/{banner}', [BannerController::class, 'update']);
    Route::delete('/banners/{banner}', [BannerController::class, 'destroy']);
    Route::post('/pages', [PageController::class, 'store']);
    Route::put('/pages/{page}', [PageController::class, 'update']);
    Route::delete('/pages/{page}', [PageController::class, 'destroy']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
});
