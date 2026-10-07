<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    private function saveBase64Image($base64String, $pathPrefix = 'banners/')
    {
        if (empty($base64String)) return $base64String;

        if (preg_match('/^data:image\/([^;]+);base64,/', $base64String, $matches)) {
            $imageData = substr($base64String, strpos($base64String, ',') + 1);
            $type = strtolower($matches[1]);
            if ($type === 'svg+xml') $type = 'svg';

            $imageData = base64_decode($imageData);
            if ($imageData === false) {
                return $base64String;
            }

            $filename = $pathPrefix . uniqid() . '_' . time() . '.' . $type;
            $disk = config('filesystems.default', 'public');
            \Illuminate\Support\Facades\Storage::disk($disk)->put($filename, $imageData);
            
            $url = rtrim(config('app.url'), '/') . '/api/images/' . $filename;
            return $url;
        }

        return $base64String;
    }

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
            'link_url' => 'nullable|string', // Support frontend sending link_url
            'position' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        if (isset($validated['link_url']) && !isset($validated['button_url'])) {
            $validated['button_url'] = $validated['link_url'];
        }

        if (!empty($validated['image_url'])) {
            $validated['image_url'] = $this->saveBase64Image($validated['image_url']);
        }

        $data = collect($validated)->except(['link_url'])->toArray();
        $banner = Banner::create($data);
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
            'link_url' => 'nullable|string', // Support frontend sending link_url
            'position' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        if (isset($validated['link_url']) && !isset($validated['button_url'])) {
            $validated['button_url'] = $validated['link_url'];
        }

        if (!empty($validated['image_url'])) {
            $validated['image_url'] = $this->saveBase64Image($validated['image_url']);
        }

        $data = collect($validated)->except(['link_url'])->toArray();
        $banner->update($data);
        return response()->json($banner);
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();
        return response()->json(['message' => 'Banner deleted successfully']);
    }
}
