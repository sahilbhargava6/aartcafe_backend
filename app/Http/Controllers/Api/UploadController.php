<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        if ($request->file('file')) {
            // Store file in the public 'uploads' directory
            // This works locally or defaults to S3 if the cloud driver is configured.
            $path = $request->file('file')->store('uploads', 'public');
            
            // Generate full URL
            $url = Storage::disk('public')->url($path);

            return response()->json([
                'status' => 'success',
                'url' => $url
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No file uploaded.'
        ], 400);
    }
}
