<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\PageController;
use App\Http\Controllers\Api\ReviewController;

// ── Website & Product Analytics ──
Route::get('/analytics/website', [AnalyticsController::class, 'website']);
Route::get('/analytics/products', [AnalyticsController::class, 'products']);

// ── Special Filters ──
Route::get('/products/new-discoveries', [ProductController::class, 'newDiscoveries']);
Route::get('/products/wedding-specials', [ProductController::class, 'weddingSpecials']);

// ── CRUD Resources ──
Route::apiResource('categories', CategoryController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('banners', BannerController::class);
Route::apiResource('pages', PageController::class);
Route::apiResource('reviews', ReviewController::class);

