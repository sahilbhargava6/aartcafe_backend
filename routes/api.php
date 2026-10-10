<?php

Route::get('/clear-base64', function () {
    try {
        \Illuminate\Support\Facades\DB::table('products')
            ->where('image', 'LIKE', 'data:image%')
            ->update(['image' => null]);
            
        $products = \App\Models\Product::where('images', 'LIKE', '%data:image%')->get();
        foreach($products as $p) {
            $p->images = null;
            $p->save();
        }
        return 'Cleared base64';
    } catch (\Throwable $e) {
        return $e->getMessage();
    }
});

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
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('storage:link'); // Added to fix broken images
        $prodsCount = \App\Models\Product::count();
        return response()->json(['status' => 'ok', 'count' => $prodsCount, 'version' => '2026-10-09-attributes-fix-v1']);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    }
});

Route::get('/convert-images-to-webp', [ProductController::class, 'convertAllImagesToWebp']);
Route::get('/fix-broken-urls', [ProductController::class, 'fixBrokenR2Urls']);
Route::match(['get', 'post'], '/sync-gdrive-folder', [ProductController::class, 'syncGoogleDriveFolder']);
Route::post('/products/{product}/upload-images', [ProductController::class, 'uploadProductImages']);
Route::post('/sync-local-images/{product}', function(\Illuminate\Http\Request $request, \App\Models\Product $product) {
    if ($request->has('image')) {
        $img = $request->input('image');
        if (str_starts_with($img, 'data:image')) {
            $url = (new \App\Http\Controllers\Api\ProductController)->saveBase64Image($img);
            if ($url) $product->image = $url;
        }
    }
    if ($request->has('images') && is_array($request->input('images'))) {
        $newImages = [];
        foreach ($request->input('images') as $img) {
            if (str_starts_with($img, 'data:image')) {
                $url = (new \App\Http\Controllers\Api\ProductController)->saveBase64Image($img);
                if ($url) $newImages[] = $url;
            } else {
                $newImages[] = $img;
            }
        }
        $product->images = $newImages;
    }
    $product->unsetRelation('category');
    $product->unsetRelation('categories');
    $product->unsetRelation('attributes');
    $product->save();
    return response()->json(['status' => 'success', 'product_id' => $product->id, 'image' => $product->image]);
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
        
        $files = [];
        if (\Illuminate\Support\Facades\Storage::disk('public')->exists('banners')) {
            $files = \Illuminate\Support\Facades\Storage::disk('public')->files('banners');
        }

        return response()->json([
            'status' => 'ok',
            'version' => '2026-10-07-fix-v6',
            'product_count' => $productsCount,
            'banners_files' => $files,
            'storage_path' => storage_path('app/public'),
            'public_storage_exists' => file_exists(public_path('storage')),
            'public_storage_is_link' => is_link(public_path('storage'))
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
Route::get('/products/festive-specials', [ProductController::class, 'festiveSpecials']);
Route::get('/products/bestsellers', [ProductController::class, 'bestsellers']);
Route::get('/products/hero-featured', [ProductController::class, 'heroFeatured']);
Route::get('/products/slug/{slug}', [ProductController::class, 'showBySlug']);

Route::post('/products/bulk-import', [ProductController::class, 'bulkImport']);

// Public Read Resources
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/pages', [PageController::class, 'index']);

// ── File Uploads & Storage Proxy ──
Route::post('/upload', [UploadController::class, 'store']);
Route::get('/images/{path}', function($path) {
    $disk = config('filesystems.default', 'public');
    if (!\Illuminate\Support\Facades\Storage::disk($disk)->exists($path)) {
        abort(404);
    }
    return \Illuminate\Support\Facades\Storage::disk($disk)->response($path);
})->where('path', '.*');

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
