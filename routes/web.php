<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/run-migrations', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        
        $user = \App\Models\User::firstOrCreate(
            ['email' => 'admin@aartcafe.com'],
            [
                'name' => 'Aartcafe Admin',
                'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
            ]
        );
        
        return response()->json([
            'status' => 'success',
            'artisan_output' => $output,
            'admin_user' => $user->email
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
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
