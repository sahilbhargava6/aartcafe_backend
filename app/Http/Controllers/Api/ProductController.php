<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index()
    {
        return response()->json(Product::with(['category', 'attributes.values'])->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:products,slug',
            'base_price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_new_discovery' => 'nullable|boolean',
            'is_wedding_special' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'required|string|max:255',
            'attributes.*.values' => 'required|array|min:1',
            'attributes.*.values.*.value' => 'required|string|max:255',
            'attributes.*.values.*.price_modifier' => 'nullable|numeric'
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        // Exclude attributes from direct product creation
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
            'category_id' => 'sometimes|required|exists:categories,id',
            'title' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:products,slug,' . $product->id,
            'base_price' => 'sometimes|required|numeric|min:0',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'is_new_discovery' => 'nullable|boolean',
            'is_wedding_special' => 'nullable|boolean',
            'is_bestseller' => 'nullable|boolean',
            'attributes' => 'nullable|array',
            'attributes.*.name' => 'required|string|max:255',
            'attributes.*.values' => 'required|array|min:1',
            'attributes.*.values.*.value' => 'required|string|max:255',
            'attributes.*.values.*.price_modifier' => 'nullable|numeric'
        ]);

        $productData = collect($validated)->except('attributes')->toArray();
        $product->update($productData);

        if ($request->has('attributes')) {
            // Delete old attributes to rebuild them
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
        $products = Product::where('is_new_discovery', true)->with(['category', 'attributes.values'])->get();
        return response()->json($products);
    }

    public function weddingSpecials()
    {
        $products = Product::where('is_wedding_special', true)->with(['category', 'attributes.values'])->get();
        return response()->json($products);
    }

    public function bestsellers()
    {
        $products = Product::where('is_bestseller', true)->with(['category', 'attributes.values'])->get();
        return response()->json($products);
    }
}
