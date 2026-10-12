<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BannerController extends Controller
{
    /**
     * An uploaded banner photo (a data: URL from the dashboard) is stored in the Cloud bucket as
     * WebP and replaced by its /media link: the image_url column only holds 255 characters.
     * Anything else (an existing link) is kept as it is.
     */
    private function saveBase64Image($image, $pathPrefix = 'banners/')
    {
        if (!is_string($image) || !str_starts_with($image, 'data:image')) {
            return $image;
        }

        $data = base64_decode(substr($image, strpos($image, ',') + 1), true);
        $webp = null;
        if ($data !== false) {
            $tmp = tempnam(sys_get_temp_dir(), 'banner');
            file_put_contents($tmp, $data);
            try {
                $webp = MediaStorage::webpFromUpload($tmp, 2000);
            } finally {
                @unlink($tmp);
            }
        }
        if ($webp === null) {
            throw ValidationException::withMessages(['image_url' => 'The banner image could not be read. Please try another image.']);
        }

        return MediaStorage::store($webp, $pathPrefix, 'webp');
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
