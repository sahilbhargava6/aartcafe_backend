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

// ── Website & Product Analytics ──
Route::get('/analytics/website', [AnalyticsController::class, 'website']);
Route::get('/analytics/products', [AnalyticsController::class, 'products']);

// ── Special Filters & Bulk Import ──
Route::get('/products/new-discoveries', [ProductController::class, 'newDiscoveries']);
Route::get('/products/wedding-specials', [ProductController::class, 'weddingSpecials']);
Route::get('/products/bestsellers', [ProductController::class, 'bestsellers']);
Route::get('/products/hero-featured', [ProductController::class, 'heroFeatured']);
Route::get('/products/slug/{slug}', [ProductController::class, 'showBySlug']);
Route::post('/products/bulk-import', [ProductController::class, 'bulkImport']);

// ── File Uploads ──
Route::post('/upload', [UploadController::class, 'store']);


// ── CRUD Resources ──
Route::apiResource('categories', CategoryController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('banners', BannerController::class);
Route::apiResource('pages', PageController::class);
Route::apiResource('reviews', ReviewController::class);

