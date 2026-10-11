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

// ── Authentication ──
Route::post('/login', [AuthController::class, 'login']);

// ── Public Order Submission & Tracking ──
Route::post('/orders', [OrderController::class, 'store']);
// A customer's reference photo for their order; public, so rate-limited (per IP; mobile networks
// share IPs between many customers, so the limit is generous)
Route::post('/order-images', [OrderController::class, 'uploadReference'])->middleware('throttle:20,10');
Route::get('/orders/track', [OrderController::class, 'track'])->middleware('throttle:10,1');

// ── Public Reviews ──
Route::get('/reviews', [ReviewController::class, 'index']);
Route::post('/reviews', [ReviewController::class, 'store']);

// ── Storefront Analytics Tracking ──
Route::post('/track', [AnalyticsController::class, 'track'])->middleware('throttle:120,1');

// ── Special Filters & Public Endpoints ──
Route::get('/products/new-discoveries', [ProductController::class, 'newDiscoveries']);
Route::get('/products/wedding-specials', [ProductController::class, 'weddingSpecials']);
Route::get('/products/festive-specials', [ProductController::class, 'festiveSpecials']);
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

// ── Storage Proxy ──
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

    // Website & Product Analytics
    Route::get('/analytics/website', [AnalyticsController::class, 'website']);
    Route::get('/analytics/products', [AnalyticsController::class, 'products']);

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

    // Uploads & Image Maintenance
    Route::post('/upload', [UploadController::class, 'store']);
    Route::post('/products/{product}/upload-images', [ProductController::class, 'uploadProductImages']);
    Route::post('/sync-gdrive-folder', [ProductController::class, 'syncGoogleDriveFolder']);
    Route::post('/sync-local-images/{product}', function(\Illuminate\Http\Request $request, \App\Models\Product $product) {
        $debugUrl = 'none';
        if ($request->has('image')) {
            $img = $request->input('image');
            if (str_starts_with($img, 'data:image')) {
                $url = (new \App\Http\Controllers\Api\ProductController)->saveBase64Image($img);
                $debugUrl = $url;
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
        return response()->json(['status' => 'success', 'product_id' => $product->id, 'image' => $product->image, 'debug_url' => $debugUrl]);
    });
});
