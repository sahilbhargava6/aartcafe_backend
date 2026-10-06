<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-products', function () {
    try {
        $products = \App\Models\Product::with(['category', 'categories', 'attributes.values'])->get();
        return response()->json(['count' => count($products), 'sample' => $products->first()]);
    } catch (\Throwable $e) {
        return response("ERROR: " . $e->getMessage() . " IN " . $e->getFile() . ":" . $e->getLine(), 200);
    }
});



// Serve uploaded images directly if storage symlink is missing on Laravel Cloud
Route::get('/storage/{path}', function ($path) {
    $filePath = 'public/' . $path;
    if (!Storage::exists($filePath)) {
        abort(404);
    }
    return Storage::response($filePath);
})->where('path', '.*');
