<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-products', function () {
    return response()->json(\App\Models\Product::all());
});



// Serve uploaded images directly if storage symlink is missing on Laravel Cloud
Route::get('/storage/{path}', function ($path) {
    $filePath = 'public/' . $path;
    if (!Storage::exists($filePath)) {
        abort(404);
    }
    return Storage::response($filePath);
})->where('path', '.*');
