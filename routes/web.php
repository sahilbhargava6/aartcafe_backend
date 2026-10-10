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
Route::get('/media/{path}', function ($path) {
    try {
        if (!Storage::disk('s3')->exists($path)) {
            // Also check 'public' disk just in case
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->response($path);
            }
            abort(404);
        }
        return Storage::disk('s3')->response($path);
    } catch (\Throwable $e) {
        abort(404);
    }
})->where('path', '.*');

Route::get('/fix-urls', function () {
    $proxy_host_old = 'https://aartcafe-backend-production-rjudvs.laravel.cloud/storage';
    $proxy_host_new = 'https://aartcafe-backend-production-rjudvs.laravel.cloud/media';
    $r2_host = 'https://fls-a2848454-7b7d-43e9-8b54-ef5486fde2ea.367be3a2035528943240074d0096e0cd.r2.cloudflarestorage.com';
    $products = App\Models\Product::all();
    $count = 0;
    foreach ($products as $p) {
        $updated = false;
        
        // fix old proxy host
        if ($p->image && str_contains($p->image, $proxy_host_old)) {
            $p->image = str_replace($proxy_host_old, $proxy_host_new, $p->image);
            $updated = true;
        }
        // fix original R2 host
        if ($p->image && str_contains($p->image, $r2_host)) {
            $p->image = str_replace($r2_host, $proxy_host_new, $p->image);
            $updated = true;
        }

        $imgs = $p->images;
        if (is_array($imgs)) {
            $newImgs = [];
            foreach ($imgs as $i) {
                $tmp = str_replace($proxy_host_old, $proxy_host_new, $i);
                $tmp = str_replace($r2_host, $proxy_host_new, $tmp);
                $newImgs[] = $tmp;
            }
            if ($newImgs !== $p->images) {
                $p->images = $newImgs;
                $updated = true;
            }
        }
        if ($updated) {
            $p->save();
            $count++;
        }
    }
    return "Fixed $count products to use media prefix!";
});
