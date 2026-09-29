<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::with(['category', 'attributes.values'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug',
            'base_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_new_discovery' => 'nullable|boolean',
            'is_wedding_special' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'is_hero_featured' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'nullable|string|max:255',
            'attributes.*.values' => 'nullable|array',
            'attributes.*.values.*.value' => 'nullable|string|max:255',
            'attributes.*.values.*.price_modifier' => 'nullable|numeric'
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $productData = collect($validated)->except('attributes')->toArray();
        $product = Product::create($productData);

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

        return response()->json($product->load('attributes.values'), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'attributes.values', 'reviews']));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:products,slug,' . $product->id,
            'base_price' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_new_discovery' => 'nullable|boolean',
            'is_wedding_special' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'is_hero_featured' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'nullable|string|max:255',
            'attributes.*.values' => 'nullable|array',
            'attributes.*.values.*.value' => 'nullable|string|max:255',
            'attributes.*.values.*.price_modifier' => 'nullable|numeric'
        ]);

        $productData = collect($validated)->except('attributes')->toArray();
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

        return response()->json($product->load('attributes.values'));
    }

    public function destroy(Product $product)
    {
        $product->attributes()->delete();
        $product->delete();
        return response()->json(['message' => 'Product deleted successfully']);
    }

    public function newDiscoveries()
    {
        return response()->json(Product::where('is_new_discovery', true)->with(['category', 'attributes.values'])->get());
    }

    public function weddingSpecials()
    {
        return response()->json(Product::where('is_wedding_special', true)->with(['category', 'attributes.values'])->get());
    }

    public function bestsellers()
    {
        return response()->json(Product::where('is_bestseller', true)->with(['category', 'attributes.values'])->get());
    }

    public function heroFeatured()
    {
        return response()->json(Product::where('is_hero_featured', true)->with(['category', 'attributes.values'])->get());
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
        $defaultCategory = Category::firstOrCreate(
            ['name' => 'General'],
            ['slug' => 'general']
        );

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
        $currentCatField = 'General';
        $currentPrice = 0;

        foreach ($productsData as $data) {
            // Check if this row starts a new Product or is a sub-row for merged cells
            $rowTitle = $data['Name of the Product'] ?? $data['Product Name'] ?? $data['title'] ?? $data['Title'] ?? null;
            $rowPrice = $data['Price'] ?? $data['₹'] ?? $data['base_price'] ?? $data['price'] ?? null;

            if (!empty($rowTitle) && strlen(trim($rowTitle)) >= 2) {
                // NEW PRODUCT ROW
                $currentTitle = trim($rowTitle);

                // Flexible Price extraction
                $cleanPrice = preg_replace('/[^0-9.]/', '', (string)($rowPrice ?? 0));
                $currentPrice = floatval($cleanPrice ?: 0);

                // Flexible Category & Flags extraction
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

                $currentCat = Category::firstOrCreate(
                    ['name' => $cleanCategoryName],
                    ['slug' => Str::slug($cleanCategoryName)]
                );

                $slug = Str::slug($currentTitle);
                $existingCount = Product::where('slug', 'LIKE', "{$slug}%")->count();
                if ($existingCount > 0) {
                    $slug = "{$slug}-" . ($existingCount + 1);
                }

                $rawImage = $data['product image'] ?? $data['Product Image'] ?? $data['image'] ?? $data['Image'] ?? null;
                $image = (!empty($rawImage) && filter_var($rawImage, FILTER_VALIDATE_URL)) ? $rawImage : null;

                $currentProduct = Product::create([
                    'category_id' => $currentCat->id,
                    'title' => $currentTitle,
                    'slug' => $slug,
                    'base_price' => $currentPrice,
                    'description' => $data['description'] ?? $data['Description'] ?? 'Handcrafted keepsake item.',
                    'image' => $image,
                    'is_new_discovery' => $isNewDiscovery,
                    'is_wedding_special' => $isWeddingSpecial,
                    'is_bestseller' => $isBestseller,
                    'is_hero_featured' => $isHeroFeatured,
                ]);

                $importedCount++;
            }

            // ATTR & VARIANT VALUES (Works for both the main row AND merged sub-rows!)
            if ($currentProduct) {
                foreach ($attributeColumnMap as $colHeader => $attrName) {
                    $colValue = $data[$colHeader] ?? null;
                    if (!empty($colValue)) {
                        $attribute = $currentProduct->attributes()->firstOrCreate(['name' => $attrName]);
                        // Comma, slash, or line separated values
                        $vals = array_map('trim', preg_split('/[,;\n]+/', $colValue));
                        foreach ($vals as $val) {
                            if (!empty($val)) {
                                $attribute->values()->firstOrCreate([
                                    'value' => $val
                                ], [
                                    'price_modifier' => 0.00
                                ]);
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'message' => "Successfully imported {$importedCount} products with custom attributes!",
            'imported_count' => $importedCount
        ], 200);
    }
}
