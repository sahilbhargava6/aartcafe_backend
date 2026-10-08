<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function saveBase64Image($base64String, $pathPrefix = 'products/')
    {
        if (empty($base64String)) {
            return null;
        }

        // Handle Google Drive links directly
        if (str_contains($base64String, 'drive.google.com') || str_contains($base64String, 'docs.google.com')) {
            $fileId = null;
            if (preg_match('/\/d\/([a-zA-Z0-9_-]+)/', $base64String, $matches)) {
                $fileId = $matches[1];
            } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $base64String, $matches)) {
                $fileId = $matches[1];
            }

            if ($fileId) {
                try {
                    $directUrl = "https://lh3.googleusercontent.com/d/{$fileId}=s1600";
                    $imageData = @file_get_contents($directUrl);
                    if ($imageData && strlen($imageData) > 0) {
                        $extension = 'webp';
                        $finalData = $imageData;
                        if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
                            $img = @imagecreatefromstring($imageData);
                            if ($img !== false) {
                                ob_start();
                                imagewebp($img, null, 85);
                                $compressed = ob_get_clean();
                                if ($compressed) $finalData = $compressed;
                                imagedestroy($img);
                            }
                        }
                        $fileName = $pathPrefix . Str::random(24) . '.' . $extension;
                        Storage::disk('public')->put($fileName, $finalData);
                        $baseUrl = env('APP_URL', 'https://aartcafe-backend-production-rjudvs.laravel.cloud');
                        return rtrim($baseUrl, '/') . '/storage/' . $fileName;
                    }
                } catch (\Throwable $e) {}
            }
        }

        if (!str_starts_with($base64String, 'data:image')) {
            return $base64String;
        }

        if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
            $data = substr($base64String, strpos($base64String, ',') + 1);
            $decodedData = base64_decode($data);
            if ($decodedData === false) {
                return $base64String;
            }

            // Compress & convert image to high-efficiency WebP format
            $finalData = $decodedData;
            $extension = 'webp';

            if (function_exists('imagecreatefromstring') && function_exists('imagewebp')) {
                try {
                    $image = @imagecreatefromstring($decodedData);
                    if ($image !== false) {
                        ob_start();
                        imagewebp($image, null, 85);
                        $compressed = ob_get_clean();
                        if ($compressed && strlen($compressed) > 0) {
                            $finalData = $compressed;
                        }
                        imagedestroy($image);
                    }
                } catch (\Throwable $e) {
                    $extension = strtolower($type[1]);
                    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                        $extension = 'jpeg';
                    }
                }
            } else {
                $extension = strtolower($type[1]);
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $extension = 'jpeg';
                }
            }

            $fileName = $pathPrefix . Str::random(24) . '.' . $extension;

            try {
                // Save to public disk to guarantee browser accessibility
                Storage::disk('public')->put($fileName, $finalData);
                $baseUrl = env('APP_URL', 'https://aartcafe-backend-production-rjudvs.laravel.cloud');
                return rtrim($baseUrl, '/') . '/storage/' . $fileName;
            } catch (\Throwable $e) {
                return $base64String;
            }
        }

        return $base64String;
    }

    public function convertAllImagesToWebp()
    {
        try {
            // Find next product containing base64 images
            $product = Product::where('image', 'LIKE', 'data:image%')
                ->orWhere('images', 'LIKE', '%data:image%')
                ->first();

            $remainingCount = Product::where('image', 'LIKE', 'data:image%')
                ->orWhere('images', 'LIKE', '%data:image%')
                ->count();

            if (!$product) {
                return response()->json([
                    'status' => 'completed',
                    'message' => 'All product images have been converted to WebP files!',
                    'remaining' => 0
                ]);
            }

            $changed = false;
            if ($product->image && str_starts_with($product->image, 'data:image')) {
                $product->image = $this->saveBase64Image($product->image);
                $changed = true;
            }

            $imagesData = is_string($product->images) ? json_decode($product->images, true) : $product->images;
            if (is_array($imagesData)) {
                $newImages = [];
                foreach ($imagesData as $img) {
                    if ($img && str_starts_with($img, 'data:image')) {
                        $newImages[] = $this->saveBase64Image($img);
                        $changed = true;
                    } else {
                        $newImages[] = $img;
                    }
                }
                $product->images = $newImages;
            }

            if ($changed) {
                $product->unsetRelation('category');
                $product->unsetRelation('categories');
                $product->unsetRelation('attributes');
                $product->save();
            }

            return response()->json([
                'status' => 'in_progress',
                'message' => "Successfully converted product ID {$product->id} ('{$product->title}') to WebP.",
                'remaining' => max(0, $remainingCount - 1),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function fixBrokenR2Urls()
    {
        try {
            $products = Product::all();
            $fixedCount = 0;
            $baseUrl = rtrim(env('APP_URL', 'https://aartcafe-backend-production-rjudvs.laravel.cloud'), '/');

            // Also ensure storage link exists
            try {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            } catch (\Throwable $e) {}

            foreach ($products as $product) {
                $changed = false;

                // Fix single main image
                if (!empty($product->image) && !str_starts_with($product->image, 'data:image')) {
                    $img = $product->image;
                    $filename = basename(parse_url($img, PHP_URL_PATH));
                    if (!empty($filename)) {
                        $newUrl = $baseUrl . '/storage/products/' . $filename;
                        if ($product->image !== $newUrl) {
                            $product->image = $newUrl;
                            $changed = true;
                        }
                    }
                }

                // Fix gallery sub-images array
                $imagesData = is_string($product->images) ? json_decode($product->images, true) : $product->images;
                if (is_array($imagesData)) {
                    $newImages = [];
                    foreach ($imagesData as $img) {
                        if (!empty($img) && !str_starts_with($img, 'data:image')) {
                            $filename = basename(parse_url($img, PHP_URL_PATH));
                            if (!empty($filename)) {
                                $newImages[] = $baseUrl . '/storage/products/' . $filename;
                                $changed = true;
                            } else {
                                $newImages[] = $img;
                            }
                        } else {
                            $newImages[] = $img;
                        }
                    }
                    if ($changed) {
                        $product->images = $newImages;
                    }
                }

                if ($changed) {
                    $product->unsetRelation('category');
                    $product->unsetRelation('categories');
                    $product->unsetRelation('attributes');
                    $product->save();
                    $fixedCount++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Successfully repaired product image paths with /storage/products/ prefix for {$fixedCount} products.",
                'fixed_count' => $fixedCount,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            // Optimized column selection & lightweight eager loading to prevent OOM
            $query = Product::select([
                'id',
                'category_id',
                'title',
                'slug',
                'base_price',
                'discount_price',
                'description',
                'image',
                'images',
                'is_new_discovery',
                'is_wedding_special',
                'is_bestseller',
                'is_hero_featured',
                'is_free_delivery',
                'is_festive_special',
                'is_active',
                'created_at',
            ])->with(['category:id,name', 'categories:id,name', 'attributes.values']);
            
            if (!$request->has('all')) {
                $query->where('is_active', true);
            }
            
            $products = $query->get();
            return response()->json($products);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug',
            'base_price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string',
            'is_new_discovery' => 'nullable|boolean',
            'is_wedding_special' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'is_hero_featured' => 'nullable|boolean',
            'is_free_delivery' => 'nullable|boolean',
            'is_festive_special' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'nullable|string|max:255',
            'attributes.*.values' => 'nullable|array',
            'attributes.*.values.*.value' => 'nullable|string|max:255',
            'attributes.*.values.*.price_modifier' => 'nullable|numeric'
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $categoryIds = $request->input('category_ids', []);
        if (empty($categoryIds) && !empty($validated['category_id'])) {
            $categoryIds = [$validated['category_id']];
        }

        if (!empty($categoryIds)) {
            $validated['category_id'] = $categoryIds[0];
        }

        if (!empty($validated['image'])) {
            $validated['image'] = $this->saveBase64Image($validated['image']);
        }
        if (!empty($validated['images']) && is_array($validated['images'])) {
            $validated['images'] = array_map(function($img) {
                return $this->saveBase64Image($img);
            }, $validated['images']);
        }

        $productData = collect($validated)->except(['attributes', 'category_ids'])->toArray();
        $product = Product::create($productData);

        if (!empty($categoryIds)) {
            $product->categories()->sync($categoryIds);
        }

        if ($request->has('attributes')) {
            foreach ($request->input('attributes') as $attrData) {
                $attribute = $product->attributes()->create([
                    'name' => $attrData['name']
                ]);
                foreach ($attrData['values'] as $valData) {
                    $attribute->values()->create([
                        'value' => $valData['value'],
                        'price_modifier' => $valData['price_modifier'] ?? 0.00
                    ]);
                }
            }
        }

        return response()->json($product->load(['category', 'categories', 'attributes.values']), 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:products,slug,' . $product->id,
            'base_price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'nullable|array',
            'images.*' => 'nullable|string',
            'is_new_discovery' => 'nullable|boolean',
            'is_wedding_special' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'is_hero_featured' => 'nullable|boolean',
            'is_free_delivery' => 'nullable|boolean',
            'is_festive_special' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'nullable|string|max:255',
            'attributes.*.values' => 'nullable|array',
            'attributes.*.values.*.value' => 'nullable|string|max:255',
            'attributes.*.values.*.price_modifier' => 'nullable|numeric'
        ]);

        $categoryIds = $request->input('category_ids', null);
        if (is_array($categoryIds)) {
            if (!empty($categoryIds)) {
                $validated['category_id'] = $categoryIds[0];
            }
            $product->categories()->sync($categoryIds);
        }

        if (!empty($validated['image'])) {
            $validated['image'] = $this->saveBase64Image($validated['image']);
        }
        if (!empty($validated['images']) && is_array($validated['images'])) {
            $validated['images'] = array_map(function($img) {
                return $this->saveBase64Image($img);
            }, $validated['images']);
        }

        $productData = collect($validated)->except(['attributes', 'category_ids'])->toArray();
        $product->update($productData);

        if ($request->has('attributes')) {
            $product->attributes()->delete();

            foreach ($request->input('attributes') as $attrData) {
                $attribute = $product->attributes()->create([
                    'name' => $attrData['name']
                ]);
                foreach ($attrData['values'] as $valData) {
                    $attribute->values()->create([
                        'value' => $valData['value'],
                        'price_modifier' => $valData['price_modifier'] ?? 0.00
                    ]);
                }
            }
        }

        return response()->json($product->load(['category', 'attributes.values']));
    }

    public function destroy(Product $product)
    {
        $product->attributes()->delete();
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function newDiscoveries()
    {
        return response()->json(Product::where('is_new_discovery', true)->where('is_active', true)->with(['category', 'attributes.values', 'reviews'])->get());
    }

    public function weddingSpecials()
    {
        return response()->json(Product::where('is_wedding_special', true)->where('is_active', true)->with(['category', 'attributes.values', 'reviews'])->get());
    }

    public function bestsellers()
    {
        return response()->json(Product::where('is_bestseller', true)->where('is_active', true)->with(['category', 'attributes.values', 'reviews'])->get());
    }

    public function heroFeatured()
    {
        return response()->json(Product::where('is_hero_featured', true)->where('is_active', true)->with(['category', 'attributes.values', 'reviews'])->get());
    }

    public function festiveSpecials()
    {
        return response()->json(Product::where('is_festive_special', true)->where('is_active', true)->with(['category', 'attributes.values', 'reviews'])->get());
    }

    public function show($id)
    {
        $product = Product::with(['category', 'categories', 'attributes.values', 'reviews'])->find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        return response()->json($product);
    }

    public function showBySlug($slug)
    {
        $product = Product::where('slug', $slug)->with(['category', 'categories', 'attributes.values', 'reviews'])->first();
        if (!$product) {
            $product = Product::where('slug', $slug)
                ->orWhere('title', 'LIKE', '%' . str_replace('-', ' ', $slug) . '%')
                ->with(['category', 'categories', 'attributes.values', 'reviews'])
                ->first();
        }

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json($product);
    }

    public function bulkImport(Request $request)
    {
        $productsData = $request->input('products', []);

        // 1. Handle uploaded .xlsx / .xls or .csv file
        if (empty($productsData) && $request->hasFile('file')) {
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $file->getRealPath();

            if (in_array($extension, ['xlsx', 'xls', 'csv'])) {
                try {
                    $spreadsheet = IOFactory::load($path);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray(null, true, true, true);

                    if (!empty($rows)) {
                        $headerRow = array_shift($rows);
                        $headers = array_map(fn($h) => trim((string)$h), array_values($headerRow));

                        foreach ($rows as $rowValues) {
                            $rowArray = array_values($rowValues);
                            if (count(array_filter($rowArray)) === 0) continue; // Skip empty rows

                            $rowObj = [];
                            foreach ($headers as $idx => $headerName) {
                                if (!empty($headerName)) {
                                    $rowObj[$headerName] = trim((string)($rowArray[$idx] ?? ''));
                                }
                            }
                            $productsData[] = $rowObj;
                        }
                    }
                } catch (\Exception $e) {
                    return response()->json(['message' => 'Error reading Excel/CSV file: ' . $e->getMessage()], 400);
                }
            }
        } elseif (empty($productsData) && $request->hasFile('csv_file')) {
            $file = $request->file('csv_file');
            $path = $file->getRealPath();
            try {
                $spreadsheet = IOFactory::load($path);
                $worksheet = $spreadsheet->getActiveSheet();
                $rows = $worksheet->toArray(null, true, true, true);
                if (!empty($rows)) {
                    $headerRow = array_shift($rows);
                    $headers = array_map(fn($h) => trim((string)$h), array_values($headerRow));
                    foreach ($rows as $rowValues) {
                        $rowArray = array_values($rowValues);
                        if (count(array_filter($rowArray)) === 0) continue;
                        $rowObj = [];
                        foreach ($headers as $idx => $headerName) {
                            if (!empty($headerName)) {
                                $rowObj[$headerName] = trim((string)($rowArray[$idx] ?? ''));
                            }
                        }
                        $productsData[] = $rowObj;
                    }
                }
            } catch (\Exception $e) {
                return response()->json(['message' => 'Error reading file: ' . $e->getMessage()], 400);
            }
        }

        if (empty($productsData)) {
            return response()->json(['message' => 'No valid product data provided'], 422);
        }

        $importedCount = 0;
        $defaultCategory = Category::where('slug', 'general')->first();
        if (!$defaultCategory) {
            $defaultCategory = Category::create(['name' => 'General', 'slug' => 'general']);
        }

        // Map column header aliases to attribute names
        $attributeColumnMap = [
            'Types of designs' => 'Types of designs',
            'Shapes' => 'Shapes',
            'Type of filling' => 'Type of filling',
            'Sizes' => 'Sizes',
            'Textual Format' => 'Textual Format',
            'Pictorial Format' => 'Pictorial Format',
            'Pictoral Format' => 'Pictorial Format',
            'Choices of Flower' => 'Choices of Flower',
            'Accessories' => 'Accessories',
            'Frames' => 'Frames',
            'Options' => 'Options',
            'Type of preservation' => 'Type of preservation',
            'Type of perservation' => 'Type of preservation',
        ];

        $currentTitle = null;
        $currentProduct = null;
        $currentCat = $defaultCategory;

        foreach ($productsData as $data) {
            $rowTitle = $data['Name of the Product'] ?? $data['Product Name'] ?? $data['title'] ?? $data['Title'] ?? null;
            
            // Extract prices embedded inside cells (e.g. "1) With: ₹299 2) Without: ₹249" or "₹2999")
            $rowPrice = $data['Price'] ?? $data['₹'] ?? $data['base_price'] ?? $data['price'] ?? null;
            
            // If Price column is empty in this row, look for numbers in Textual/Pictoral cells
            if (empty($rowPrice)) {
                $textualCell = $data['Textual Format'] ?? '';
                $pictoralCell = $data['Pictoral Format'] ?? $data['Pictorial Format'] ?? '';
                $match = [];
                if (preg_match('/(?:₹|Rs\.?|With|Without)?\s*[:\-]?\s*(\d+)/iu', $textualCell, $match) || preg_match('/(?:₹|Rs\.?|With|Without)?\s*[:\-]?\s*(\d+)/iu', $pictoralCell, $match)) {
                    $rowPrice = $match[1];
                }
            }

            if (!empty($rowTitle) && strlen(trim($rowTitle)) >= 2) {
                // NEW PRODUCT ROW
                $currentTitle = trim($rowTitle);

                $cleanPrice = preg_replace('/[^0-9.]/', '', (string)($rowPrice ?? 0));
                $basePrice = floatval($cleanPrice ?: 0);

                // Category & Flags
                $catField = $data['Category of the Product'] ?? $data['Category of the Product, also best seller etc'] ?? $data['Category'] ?? $data['category'] ?? 'General';
                $tagsField = $data['also best seller etc'] ?? $data['Tags'] ?? '';
                $combinedCatTags = $catField . ' ' . $tagsField;

                $isBestseller = filter_var($data['is_bestseller'] ?? $data['Is Bestseller'] ?? false, FILTER_VALIDATE_BOOLEAN) ||
                    stripos($combinedCatTags, 'bestseller') !== false || stripos($combinedCatTags, 'best seller') !== false;

                $isWeddingSpecial = stripos($combinedCatTags, 'wedding') !== false ||
                    filter_var($data['is_wedding_special'] ?? $data['Is Wedding Special'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $isNewDiscovery = stripos($combinedCatTags, 'new') !== false ||
                    filter_var($data['is_new_discovery'] ?? $data['Is New Discovery'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $isHeroFeatured = filter_var($data['is_hero_featured'] ?? $data['Is Hero Featured'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $cleanCategoryName = 'General';
                if (!empty($catField)) {
                    $cleaned = preg_replace('/(bestseller|best seller|new discovery|wedding special)/i', '', $catField);
                    $cleaned = trim($cleaned, " \t\n\r\0\x0B,-");
                    if (!empty($cleaned)) {
                        $cleanCategoryName = $cleaned;
                    }
                }

                $catSlug = Str::slug($cleanCategoryName) ?: 'general';
                $currentCat = Category::where('slug', $catSlug)->first();
                if (!$currentCat) {
                    $currentCat = Category::create(['name' => $cleanCategoryName, 'slug' => $catSlug]);
                }

                $slug = Str::slug($currentTitle);
                $existingCount = Product::where('slug', 'LIKE', "{$slug}%")->count();
                if ($existingCount > 0) {
                    $slug = "{$slug}-" . ($existingCount + 1);
                }

                $rawImage = $data['product image'] ?? $data['Product Image'] ?? $data['image'] ?? $data['Image'] ?? null;
                if (!empty($rawImage) && str_starts_with($rawImage, 'data:image')) {
                    $image = $this->saveBase64Image($rawImage);
                } else {
                    $image = (!empty($rawImage) && filter_var($rawImage, FILTER_VALIDATE_URL)) ? $rawImage : null;
                }

                $currentProduct = Product::create([
                    'category_id' => $currentCat->id,
                    'title' => $currentTitle,
                    'slug' => $slug,
                    'base_price' => $basePrice,
                    'description' => $data['description'] ?? $data['Description'] ?? 'Handcrafted keepsake item.',
                    'image' => $image,
                    'is_new_discovery' => $isNewDiscovery,
                    'is_wedding_special' => $isWeddingSpecial,
                    'is_bestseller' => $isBestseller,
                    'is_hero_featured' => $isHeroFeatured,
                ]);

                $importedCount++;
            } elseif ($currentProduct && !empty($rowPrice) && $currentProduct->base_price == 0) {
                // Update product base_price if found in a subsequent sub-row (e.g. Calender Frames row 9 ₹2999)
                $cleanPrice = preg_replace('/[^0-9.]/', '', (string)$rowPrice);
                if ($cleanPrice > 0) {
                    $currentProduct->update(['base_price' => floatval($cleanPrice)]);
                }
            }

            // PROCESS ATTRIBUTES & PRICE MODIFIERS ACROSS ROWS
            if ($currentProduct) {
                foreach ($attributeColumnMap as $colHeader => $attrName) {
                    $colValue = $data[$colHeader] ?? null;
                    if (!empty($colValue)) {
                        // Check if cell contains price rules like "1) With: ₹299 2) Without: ₹249"
                        $lines = array_map('trim', preg_split('/[\n;]+/', $colValue));
                        foreach ($lines as $line) {
                            if (empty($line)) continue;

                            // Clean attribute value label (remove numbers/bullets like "1) ")
                            $cleanValue = trim(preg_replace('/^\d+[\.\)]\s*/', '', $line));
                            if (in_array(strtolower($cleanValue), ['na', 'n/a', 'none', '-', 'null', ''])) {
                                continue; // Skip NA or placeholder values
                            }

                            $attribute = $currentProduct->attributes()->firstOrCreate(['name' => $attrName]);

                            // Parse inline price modifiers e.g. "With: ₹299" or "Without: ₹249" or "With: 499"
                            $priceMod = 0.00;
                            if (preg_match('/(?:₹|Rs\.?|With|Without)\s*[:\-]?\s*(\d+)/iu', $line, $priceMatch)) {
                                $extractedVal = floatval($priceMatch[1]);
                                // If base_price is 0, use first extracted price as base_price
                                if ($currentProduct->base_price == 0) {
                                    $currentProduct->update(['base_price' => $extractedVal]);
                                } else {
                                    $priceMod = max(0, $extractedVal - $currentProduct->base_price);
                                }
                            }

                            $attribute->values()->firstOrCreate(
                                ['value' => $cleanValue],
                                ['price_modifier' => $priceMod]
                            );
                        }
                    }
                }

                // Delete any attributes that ended up with 0 values
                foreach ($currentProduct->attributes as $attr) {
                    if ($attr->values()->count() === 0) {
                        $attr->delete();
                    }
                }
            }
        }

        return response()->json([
            'message' => "Successfully imported {$importedCount} products with custom attributes!",
            'imported_count' => $importedCount
        ], 200);
    }

    public function syncGoogleDriveFolder(Request $request)
    {
        try {
            $folderUrl = $request->input('folder_url');
            if (empty($folderUrl)) {
                return response()->json(['error' => 'Please provide a valid Google Drive folder URL.'], 400);
            }

            // Extract main folder ID cleanly
            $folderId = null;
            if (preg_match('/\/folders\/([a-zA-Z0-9_-]+)/', $folderUrl, $matches)) {
                $folderId = $matches[1];
            } elseif (preg_match('/id=([a-zA-Z0-9_-]+)/', $folderUrl, $matches)) {
                $folderId = $matches[1];
            }

            if (!$folderId) {
                return response()->json(['error' => 'Could not extract Google Drive folder ID from the link.'], 400);
            }

            // Fetch public folder webpage with User-Agent header
            $opts = [
                "http" => [
                    "method" => "GET",
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
                    "follow_location" => 1
                ]
            ];
            $context = stream_context_create($opts);
            $html = @file_get_contents("https://drive.google.com/drive/folders/{$folderId}", false, $context);
            if (!$html) {
                return response()->json(['error' => 'Unable to read the Google Drive folder. Please ensure the link sharing is set to "Anyone with the link".'], 400);
            }

            // Extract all item IDs and titles from Google Drive HTML script payloads
            $driveItems = [];
            
            // Pattern 1: AF_initDataCallback JS payloads
            preg_match_all('/"([a-zA-Z0-9_-]{25,})",\["([^"\n\r]{2,80})"/u', $html, $matches1, PREG_SET_ORDER);
            preg_match_all('/\["([a-zA-Z0-9_-]{25,})","([^"\n\r]{2,80})"/u', $html, $matches2, PREG_SET_ORDER);
            
            foreach (array_merge($matches1, $matches2) as $m) {
                $fId = $m[1];
                $fName = trim(json_decode('"' . str_replace('"', '\"', $m[2]) . '"') ?? $m[2]);
                if (strlen($fName) >= 2 && !str_starts_with($fName, 'http') && !str_contains($fName, '/') && !str_contains($fName, '\\')) {
                    $driveItems[$fName] = $fId;
                }
            }

            $matchedCount = 0;
            $products = Product::all();
            $matchedDetails = [];

            foreach ($products as $product) {
                $matchedFileId = null;
                $prodTitle = trim($product->title);
                
                // 1. Direct match by exact title
                if (isset($driveItems[$prodTitle])) {
                    $matchedFileId = $driveItems[$prodTitle];
                } else {
                    // 2. Fuzzy match title in Google Drive filenames/folder names
                    foreach ($driveItems as $name => $fId) {
                        if (stripos($name, $prodTitle) !== false || stripos($prodTitle, $name) !== false) {
                            $matchedFileId = $fId;
                            break;
                        }
                    }
                }

                if ($matchedFileId) {
                    $directUrl = "https://lh3.googleusercontent.com/d/{$matchedFileId}=s1600";
                    $imageData = @file_get_contents($directUrl, false, $context);
                    if ($imageData && strlen($imageData) > 0) {
                        $img = @imagecreatefromstring($imageData);
                        $finalData = $imageData;
                        if ($img !== false) {
                            ob_start();
                            imagewebp($img, null, 85);
                            $compressed = ob_get_clean();
                            if ($compressed) $finalData = $compressed;
                            imagedestroy($img);
                        }
                        $fileName = 'products/' . Str::random(24) . '.webp';
                        Storage::disk('public')->put($fileName, $finalData);
                        $baseUrl = env('APP_URL', 'https://aartcafe-backend-production-rjudvs.laravel.cloud');
                        
                        $product->image = rtrim($baseUrl, '/') . '/storage/' . $fileName;
                        $product->unsetRelation('category');
                        $product->unsetRelation('categories');
                        $product->unsetRelation('attributes');
                        $product->save();
                        $matchedCount++;
                        $matchedDetails[] = ['product' => $prodTitle, 'file_id' => $matchedFileId];
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'folder_id' => $folderId,
                'message' => "Successfully matched and updated {$matchedCount} products from Google Drive!",
                'matched_count' => $matchedCount,
                'matched_details' => $matchedDetails,
                'detected_drive_items_count' => count($driveItems),
                'detected_drive_items_sample' => array_slice($driveItems, 0, 10)
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }

    public function uploadProductImages(Request $request, Product $product)
    {
        try {
            $uploadedFiles = $request->file('images');
            if (empty($uploadedFiles)) {
                return response()->json(['error' => 'No image files provided.'], 400);
            }

            $replace = filter_var($request->input('replace', false), FILTER_VALIDATE_BOOLEAN);

            $newUrls = [];
            foreach ($uploadedFiles as $file) {
                if (!$file->isValid()) continue;
                $mime = $file->getClientMimeType() ?: 'image/jpeg';
                $contents = file_get_contents($file->getRealPath());
                if (empty($contents)) continue;
                $dataUrl = 'data:' . $mime . ';base64,' . base64_encode($contents);
                $savedUrl = $this->saveBase64Image($dataUrl);
                if ($savedUrl) {
                    $newUrls[] = $savedUrl;
                }
            }

            if (!empty($newUrls)) {
                if ($replace || empty($product->image) || str_contains($product->image, 'unsplash.com') || str_contains($product->image, 'placeholder')) {
                    $product->image = $newUrls[0];
                }

                $existingImages = $replace ? [] : (is_array($product->images) ? $product->images : (json_decode($product->images, true) ?? []));
                if (!is_array($existingImages)) $existingImages = [];

                $mergedImages = array_values(array_unique(array_merge($existingImages, $newUrls)));
                $product->images = $mergedImages;

                $product->unsetRelation('category');
                $product->unsetRelation('categories');
                $product->unsetRelation('attributes');
                $product->save();
            }

            return response()->json([
                'status' => 'success',
                'uploaded_count' => count($newUrls),
                'total_images' => count($product->images ?? []),
                'main_image' => $product->image
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'line' => $e->getLine()], 500);
        }
    }
}
