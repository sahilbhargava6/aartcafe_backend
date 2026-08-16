<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        return response()->json(Banner::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'required|string',
            'button_text' => 'nullable|string',
            'button_url' => 'nullable|string',
            'position' => 'nullable|string'
        ]);

        $banner = Banner::create($validated);
        return response()->json($banner, 201);
    }

    public function show(Banner $banner)
    {
        return response()->json($banner);
    }

    public function update(Request $request, Banner $banner)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'image_url' => 'sometimes|required|string',
            'button_text' => 'nullable|string',
            'button_url' => 'nullable|string',
            'position' => 'sometimes|required|string'
        ]);

        $banner->update($validated);
        return response()->json($banner);
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();
        return response()->json(['message' => 'Banner deleted successfully']);
    }
}
