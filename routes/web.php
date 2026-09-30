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
Route::get('/test-auth', function () {
    try {
        $user = \App\Models\User::where('email', 'admin@aartcafe.com')->first();
        if (!$user) {
            return response()->json(['error' => 'User not found']);
        }
        
        $passwordCheck = \Illuminate\Support\Facades\Hash::check('admin123', $user->password);
        $token = $user->createToken('admin-token')->plainTextToken;
        
        return response()->json([
            'user_exists' => true,
            'password_match' => $passwordCheck,
            'token_generated' => $token
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'error_message' => $e->getMessage(),
            'error_file' => $e->getFile() . ':' . $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
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
