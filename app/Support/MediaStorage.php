<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uploaded images live on the default disk (the Laravel Cloud bucket) and are served
 * through the /media route.
 */
class MediaStorage
{
    // Public address of a stored file (or of a folder, for prefix checks)
    public static function url(string $path = ''): string
    {
        return rtrim(env('APP_URL', 'https://aartcafe-backend-production-rjudvs.laravel.cloud'), '/') . '/media/' . $path;
    }

    /**
     * The bytes of an image given by its public address: a /media file from our own disks,
     * or anything else fetched over HTTP. Null when it can't be read.
     */
    public static function read(?string $url): ?string
    {
        if (!$url) return null;
        $marker = '/media/';
        if (($at = strpos($url, $marker)) !== false) {
            $path = urldecode(substr($url, $at + strlen($marker)));
            foreach (array_unique([config('filesystems.default'), 's3', 'public']) as $disk) {
                try {
                    if (Storage::disk($disk)->exists($path)) return Storage::disk($disk)->get($path);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
        if (!str_starts_with($url, 'http')) return null;
        try {
            $response = Http::timeout(10)->get($url);
            return $response->successful() ? $response->body() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function store(string $data, string $pathPrefix = 'products/', string $extension = 'webp'): string
    {
        $fileName = $pathPrefix . Str::random(24) . '.' . $extension;

        // No 'public' visibility: the Cloud bucket is private and files are served through /media
        if (Storage::disk(config('filesystems.default'))->put($fileName, $data)) {
            return self::url($fileName);
        }

        // Only a dev machine without bucket credentials may use the local disk;
        // on Laravel Cloud that disk is wiped on every deploy.
        if (app()->environment('local') && Storage::disk('public')->put($fileName, $data)) {
            return self::url($fileName);
        }

        throw new \RuntimeException("Could not save image {$fileName} to the storage bucket.");
    }

    /**
     * An uploaded photo as WebP: turned upright per its EXIF data and at most $max pixels on its
     * longest side. Re-encoding also drops anything in the file that isn't image data.
     * Returns null when the file can't be read as an image.
     */
    public static function webpFromUpload(string $path, int $max = 1600): ?string
    {
        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (!$img) return null;
        if (!imageistruecolor($img)) imagepalettetotruecolor($img);

        // Phone photos keep their rotation in EXIF, which GD ignores
        if (function_exists('exif_read_data')) {
            switch (@exif_read_data($path)['Orientation'] ?? 1) {
                case 2: imageflip($img, IMG_FLIP_HORIZONTAL); break;
                case 3: $img = imagerotate($img, 180, 0); break;
                case 4: imageflip($img, IMG_FLIP_VERTICAL); break;
                case 5: imageflip($img, IMG_FLIP_VERTICAL); $img = imagerotate($img, -90, 0); break;
                case 6: $img = imagerotate($img, -90, 0); break;
                case 7: imageflip($img, IMG_FLIP_VERTICAL); $img = imagerotate($img, 90, 0); break;
                case 8: $img = imagerotate($img, 90, 0); break;
            }
        }

        $width = imagesx($img);
        $height = imagesy($img);
        $scale = min(1, $max / max($width, $height));
        if ($scale < 1) {
            $resized = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
            $img = $resized;
        }

        imagesavealpha($img, true);
        ob_start();
        imagewebp($img, null, 82);
        $data = ob_get_clean();
        return $data ?: null;
    }
}
