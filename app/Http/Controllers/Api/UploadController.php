<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
        ]);

        if ($request->file('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType() ?: 'image/jpeg';
            $contents = file_get_contents($file->getRealPath());
            $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($contents);

            return response()->json([
                'status' => 'success',
                'url' => $dataUrl
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No file uploaded.'
        ], 400);
    }
}
