<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::with(['parent', 'children'])->withCount('products')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:categories,slug',
            'parent_id' => 'nullable|exists:categories,id'
        ]);

        if ($error = $this->parentError($validated['parent_id'] ?? null)) {
            return response()->json(['message' => $error], 422);
        }

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category = Category::create($validated);
        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category->load('products'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|unique:categories,slug,' . $category->id,
            'parent_id' => 'nullable|exists:categories,id'
        ]);

        if ($error = $this->parentError($validated['parent_id'] ?? null, $category)) {
            return response()->json(['message' => $error], 422);
        }

        $category->update($validated);
        return response()->json($category);
    }

    /**
     * Categories have two levels: a parent must be a main category, and a category that has
     * sub-categories stays a main one (which also rules out loops). Returns the problem, if any.
     */
    private function parentError($parentId, ?Category $category = null): ?string
    {
        if (!$parentId) return null;
        if ($category && (int) $parentId === $category->id) {
            return 'A category cannot be its own parent.';
        }
        if (Category::whereKey($parentId)->whereNotNull('parent_id')->exists()) {
            return 'Pick a main category as the parent. Sub-categories cannot have sub-categories of their own.';
        }
        if ($category && $category->children()->exists()) {
            return 'This category has sub-categories, so it cannot become a sub-category itself.';
        }
        return null;
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
