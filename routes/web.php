<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-db-products', function () {
    try {
        return response()->json(\App\Models\Product::select('id', 'title')->get());
    } catch (\Throwable $e) {
        return response("DB ERROR: " . $e->getMessage() . " AT " . $e->getFile() . ":" . $e->getLine(), 200, ['Content-Type' => 'text/plain']);
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
