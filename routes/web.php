<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/debug-err', function () {
    try {
        $data = \App\Models\Product::with(['category', 'attributes.values'])->orderBy('id', 'asc')->get();
        return response()->json(['ok' => true, 'count' => count($data)]);
    } catch (\Throwable $e) {
        return response("EXCEPTION: " . $e->getMessage() . " IN " . $e->getFile() . ":" . $e->getLine() . "\nTRACED:\n" . $e->getTraceAsString(), 200, ['Content-Type' => 'text/plain']);
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
