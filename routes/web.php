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







// Serve uploaded images directly from the default disk (Bucket)
Route::get('/storage/{path}', function ($path) {
    try {
        $exists = Storage::disk('s3')->exists($path);
        if (!$exists) {
            // Also check 'public' disk just in case
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->response($path);
            }
            
            // Debug info
            return response()->json([
                'error' => 'File not found',
                'path' => $path,
                's3_disk_config' => config('filesystems.disks.s3'),
                'default_disk' => config('filesystems.default')
            ], 404);
        }
        return Storage::disk('s3')->response($path);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'path' => $path
        ], 500);
    }
})->where('path', '.*');

Route::get('/fix-urls', function () {
    $r2_host = 'https://fls-a2848454-7b7d-43e9-8b54-ef5486fde2ea.367be3a2035528943240074d0096e0cd.r2.cloudflarestorage.com';
    $proxy_host = 'https://aartcafe-backend-production-rjudvs.laravel.cloud/storage';
    $products = App\Models\Product::all();
    $count = 0;
    foreach ($products as $p) {
        $updated = false;
        if ($p->image && str_contains($p->image, $r2_host)) {
            $p->image = str_replace($r2_host, $proxy_host, $p->image);
            $updated = true;
        }
        $imgs = $p->images;
        if (is_array($imgs)) {
            $newImgs = [];
            foreach ($imgs as $i) {
                $newImgs[] = str_replace($r2_host, $proxy_host, $i);
            }
            $p->images = $newImgs;
            $updated = true;
        }
        if ($updated) {
            $p->save();
            $count++;
        }
    }
    return "Fixed $count products!";
});
