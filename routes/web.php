<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/media/{path}', function ($path) {
    // Same disk the uploads are written to (the Laravel Cloud bucket); 'public' is
    // only for files saved on a local dev machine.
    foreach (array_unique([config('filesystems.default'), 's3', 'public']) as $disk) {
        try {
            if (Storage::disk($disk)->exists($path)) {
                // File names are random and never reused, so they can be cached forever
                return Storage::disk($disk)->response($path, null, [
                    'Cache-Control' => 'public, max-age=31536000, immutable',
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
    abort(404);
})->where('path', '.*');
