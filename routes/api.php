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



Route::get('/setup-admin', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('storage:link'); // Added to fix broken images
        $prods = \App\Models\Product::all();
        return response()->json(['status' => 'ok', 'count' => count($prods), 'version' => '2026-10-07-fix-v2']);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    }
});

Route::get('/debug', function () {
    try {
        // Clear OPcache to force reloading PHP files
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // Try to instantiate the controller to check for class loading errors
        $controllerClass = \App\Http\Controllers\Api\ProductController::class;
        
        $productsCount = \App\Models\Product::count();
        
        $maxImageLen = \Illuminate\Support\Facades\DB::table('products')
            ->selectRaw('MAX(LENGTH(image)) as max_len')->value('max_len');
            
        $maxImagesLen = \Illuminate\Support\Facades\DB::table('products')
            ->selectRaw('MAX(LENGTH(images)) as max_len')->value('max_len');

        return response()->json([
            'status' => 'ok',
            'version' => '2026-10-07-fix-v5',
            'product_count' => $productsCount,
            'max_image_len' => $maxImageLen,
            'max_images_len' => $maxImagesLen,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

Route::get('/logs', function () {
    $logFile = storage_path('logs/laravel.log');
    if (file_exists($logFile)) {
        // Get the last 1000 lines or so
        $lines = file($logFile);
        $lastLines = array_slice($lines, -200);
        return response(implode("", $lastLines))->header('Content-Type', 'text/plain');
    }
    return 'No log file found.';
});

// ── Authentication ──
Route::post('/login', [AuthController::class, 'login']);

// ── Public Order Submission & Tracking ──
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/track', [OrderController::class, 'track']);

// ── Public Reviews ──
Route::get('/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store']);

// ── Website & Product Analytics ──
Route::get('/analytics/website', [AnalyticsController::class, 'website']);
Route::get('/analytics/products', [AnalyticsController::class, 'products']);

// Fix Images 
Route::get('/fix-images', [ProductController::class, 'fixImages']);

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
