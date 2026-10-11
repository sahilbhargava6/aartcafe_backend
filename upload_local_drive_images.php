<?php

// Uploads the product photos from the local Google Drive download to the live site.
// Each folder is matched to a live product by name; its photos are rotated per EXIF,
// shrunk to 1600px max, saved as WebP and uploaded through the admin API, which stores
// them in the Cloud bucket so they survive deploys.
//
// Usage: php upload_local_drive_images.php [photosDir] [--dry] [--only=ID,ID] [--force]
//   photosDir  defaults to d:/aartcafe/extracted_images/Aartcafe
//   --dry      resize and report only, upload nothing
//   --only     limit the run to these product ids
//   --force    also replace products whose current photo already loads
// Needs AARTCAFE_ADMIN_TOKEN (an admin API token, from POST /api/login) unless --dry.

$baseUrl = 'https://aartcafe-backend-production-rjudvs.laravel.cloud';

$args = array_slice($argv, 1);
$dirArg = current(array_filter($args, fn($a) => !str_starts_with($a, '--')));
$dir = rtrim(str_replace('\\', '/', $dirArg ?: 'd:/aartcafe/extracted_images/Aartcafe'), '/');
$dry = in_array('--dry', $args, true);
$force = in_array('--force', $args, true);
$only = null;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--only=')) $only = array_map('intval', explode(',', substr($arg, 7)));
}
$adminToken = getenv('AARTCAFE_ADMIN_TOKEN');
if (!is_dir($dir)) die("Photos folder not found: {$dir}\n");
if (!$dry && !$adminToken) die("Set AARTCAFE_ADMIN_TOKEN to an admin API token first\n");

$products = [];
foreach (json_decode(file_get_contents("{$baseUrl}/api/products?all=1"), true) ?: [] as $p) $products[$p['id']] = $p;
if (!$products) die("Could not load products from the live API\n");

// Folder (or folder/subfolder) => product id, where the names differ or a plain "contains" match would be wrong
$overrides = [
    'Light Frame' => 8,
    'Swinging Light Frame' => 39,                 // would otherwise land on #8 "Light Frame"
    'Alphabet Keychains' => 45,
    'Book Style Frame' => 34,                     // "Book Style MANKA Frame"
    'Flower Based Frame' => 12,                   // "Flower Based Frame (Crushed Base)"
    'Hastag Light Stand' => 18,                   // spelling
    'Locket' => 6,                                // "Heartheld Locket"
    'Mobile Covers' => 48,
    'NayaSama Frame' => 57,                       // title has an emoji
    'Full Vermala Frame' => 64,                   // "Full Varmala Frame"; photos are in its subfolders
    'Half n Half Loose Petal Frame/Wedding Card and Vermala Frame' => 68,
];
$recursive = ['Full Vermala Frame'];

$norm = fn($s) => trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($s)));
$byTitle = [];
foreach ($products as $id => $p) $byTitle[$norm($p['title'])][] = $id;

$isImage = fn($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f);
$listImages = function (string $path, bool $deep) use (&$listImages, $isImage) {
    $out = [];
    foreach (scandir($path) as $f) {
        if ($f === '.' || $f === '..') continue;
        $full = "{$path}/{$f}";
        if (is_dir($full)) { if ($deep) $out = array_merge($out, $listImages($full, true)); }
        elseif ($isImage($f)) $out[] = $full;
    }
    natcasesort($out);
    return array_values($out);
};

// Build the plan: product id => list of photo files. Only exact (normalised) title matches
// and the overrides above are used, so a folder never lands on a similarly named product.
$plan = [];
$unmatched = [];
foreach (scandir($dir) as $folder) {
    if ($folder === '.' || $folder === '..' || !is_dir("{$dir}/{$folder}")) continue;
    $id = $overrides[$folder] ?? (count($byTitle[$norm($folder)] ?? []) === 1 ? $byTitle[$norm($folder)][0] : null);
    if (!$id || !isset($products[$id])) { $unmatched[] = $folder; continue; }
    $plan[$id] = array_merge($plan[$id] ?? [], $listImages("{$dir}/{$folder}", in_array($folder, $recursive, true)));
}
foreach ($overrides as $key => $id) {
    if (str_contains($key, '/') && is_dir("{$dir}/{$key}") && isset($products[$id])) $plan[$id] = $listImages("{$dir}/{$key}", false);
}
$plan = array_filter($plan);
$missing = array_diff_key($products, $plan);
if ($only) $plan = array_intersect_key($plan, array_flip($only));

function prepareImage(string $path, int $max = 1600): ?string
{
    $img = @imagecreatefromstring(file_get_contents($path));
    if (!$img) return null;
    if (!imageistruecolor($img)) imagepalettetotruecolor($img);
    // Phone photos keep their rotation in EXIF, which GD ignores
    if (preg_match('/\.jpe?g$/i', $path) && function_exists('exif_read_data')) {
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
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, $max / max($w, $h));
    if ($scale < 1) {
        $nw = (int) round($w * $scale);
        $nh = (int) round($h * $scale);
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $img = $dst;
    }
    imagesavealpha($img, true);
    ob_start();
    imagewebp($img, null, 82);
    $data = ob_get_clean();
    return $data ?: null;
}

function imageLoads(?string $url): bool
{
    if (!$url || !str_starts_with($url, 'http')) return false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 30]);
    curl_exec($ch);
    return curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
}

function uploadBatch(string $baseUrl, string $token, int $id, array $files, bool $replace): array
{
    $post = ['replace' => $replace ? '1' : '0'];
    foreach ($files as $i => $file) $post["images[{$i}]"] = new CURLFile($file, 'image/webp', basename($file));
    $ch = curl_init("{$baseUrl}/api/products/{$id}/upload-images");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_HTTPHEADER => ['Accept: application/json', "Authorization: Bearer {$token}"],
    ]);
    $body = curl_exec($ch);
    return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $body === false ? curl_error($ch) : $body];
}

$tmpDir = sys_get_temp_dir() . '/aartcafe-upload-' . getmypid();
@mkdir($tmpDir);
$totalIn = $totalOut = $done = 0;
$failed = [];
$kept = [];
foreach ($plan as $id => $files) {
    // Leave products alone whose photo already works (e.g. replaced from the dashboard)
    if (!$force && imageLoads($products[$id]['image'] ?? null)) {
        $kept[] = "#{$id} {$products[$id]['title']}";
        continue;
    }

    $prepared = [];
    $bytes = 0;
    foreach ($files as $n => $file) {
        $webp = prepareImage($file);
        if ($webp === null) { echo "  could not read {$file}\n"; continue; }
        $out = "{$tmpDir}/{$id}-" . str_pad((string) $n, 3, '0', STR_PAD_LEFT) . '.webp';
        file_put_contents($out, $webp);
        $prepared[] = $out;
        $totalIn += filesize($file);
        $totalOut += strlen($webp);
        $bytes += strlen($webp);
    }
    printf("#%d %s: %d photos, %.1f MB after resizing\n", $id, $products[$id]['title'], count($prepared), $bytes / 1048576);

    if (!$dry && $prepared) {
        // Small batches stay well under the server's upload limits; the first one replaces the old images
        $ok = true;
        foreach (array_chunk($prepared, 4) as $b => $batch) {
            [$code, $body] = uploadBatch($baseUrl, $adminToken, $id, $batch, $b === 0);
            if ($code < 200 || $code >= 300) {
                $ok = false;
                echo "  FAILED batch " . ($b + 1) . ": HTTP {$code} " . substr((string) $body, 0, 300) . "\n";
                break;
            }
        }
        if ($ok) $done++; else $failed[] = $id;
    }
    foreach ($prepared as $f) @unlink($f);
}
@rmdir($tmpDir);

printf("\nPhotos: %.0f MB originals -> %.0f MB after resizing\n", $totalIn / 1048576, $totalOut / 1048576);
if (!$dry) echo "Products updated: {$done}" . ($failed ? ", failed: " . implode(', ', $failed) : '') . "\n";
echo 'Already had working photos, kept (use --force to replace): ' . count($kept) . "\n";
echo 'Folders with no product: ' . (implode(' | ', $unmatched) ?: 'none') . "\n";
echo 'Products with no photos in the folder (' . count($missing) . '): ' . implode(' | ', array_map(fn($p) => "#{$p['id']} {$p['title']}", $missing)) . "\n";
