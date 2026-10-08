<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

$extractedDir = 'd:/aartcafe/extracted_images/Aartcafe';
if (!is_dir($extractedDir)) {
    die("Extracted dir not found\n");
}

$folders = array_diff(scandir($extractedDir), ['.', '..']);
$baseUrl = 'https://aartcafe-backend-production-rjudvs.laravel.cloud';

// Fetch production products via API
$json = file_get_contents($baseUrl . '/api/products');
$products = json_decode($json, true) ?? [];

echo "Found " . count($folders) . " folders in local Drive download.\n";
echo "Found " . count($products) . " products from Production API.\n\n";

$matchedCount = 0;

foreach ($folders as $folderName) {
    $folderPath = $extractedDir . '/' . $folderName;
    if (!is_dir($folderPath)) continue;

    // Get all image files inside this product folder
    $files = array_values(array_filter(scandir($folderPath), function($f) use ($folderPath) {
        if (in_array($f, ['.', '..'])) return false;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
    }));

    if (empty($files)) continue;

    // Find matching product in database
    $matchedProduct = null;
    $cleanFolderName = trim(strtolower($folderName));

    foreach ($products as $p) {
        $cleanTitle = trim(strtolower($p['title']));
        if ($cleanTitle === $cleanFolderName || str_contains($cleanTitle, $cleanFolderName) || str_contains($cleanFolderName, $cleanTitle)) {
            $matchedProduct = $p;
            break;
        }
    }

    if ($matchedProduct) {
        echo "MATCHED: Folder '{$folderName}' -> Product ID {$matchedProduct['id']} ('{$matchedProduct['title']}')\n";
        
        $savedImages = [];
        $firstImage = null;

        foreach ($files as $idx => $f) {
            $filePath = $folderPath . '/' . $f;
            $imageData = file_get_contents($filePath);
            if (!$imageData) continue;

            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if ($ext === 'jpg') $ext = 'jpeg';
            $base64 = 'data:image/' . $ext . ';base64,' . base64_encode($imageData);

            if ($idx === 0) {
                $firstImage = $base64;
            }
            $savedImages[] = $base64;
        }

        if ($firstImage) {
            // Update product via dedicated sync-local-images API
            $updateData = [
                'image' => $firstImage,
                'images' => $savedImages
            ];

            $ch = curl_init($baseUrl . '/api/sync-local-images/' . $matchedProduct['id']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            $res = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode >= 200 && $httpCode < 300) {
                echo "SUCCESS: Uploaded images for ID {$matchedProduct['id']} ('{$matchedProduct['title']}')\n";
                $matchedCount++;
            } else {
                echo "FAILED to update ID {$matchedProduct['id']}: HTTP {$httpCode} - {$res}\n";
            }
        }
    } else {
        echo "NO MATCH for folder: '{$folderName}'\n";
    }
}

echo "\nSUCCESSFULLY MATCHED AND UPDATED IMAGES FOR {$matchedCount} PRODUCTS ON PRODUCTION!\n";
