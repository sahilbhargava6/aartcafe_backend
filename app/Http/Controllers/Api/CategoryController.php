<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('parents:id')->withCount('products')->get();
        $categories->each(function (Category $category) {
            $category->setAttribute('parent_ids', $category->parents->pluck('id')->values());
            $category->unsetRelation('parents');
        });
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'parent_ids' => 'sometimes|array',
            'parent_ids.*' => 'integer|distinct|exists:categories,id',
        ]);

        $parentIds = $this->requestedParents($request);

        if (empty($validated['slug'])) {
            $baseSlug = Str::slug($validated['name']) ?: 'category';
            $slug = $baseSlug;
            $counter = 1;
            while (Category::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }
            $validated['slug'] = $slug;
        } else {
            if (Category::where('slug', $validated['slug'])->exists()) {
                return response()->json(['message' => 'The category slug already exists.'], 422);
            }
        }

        try {
            $category = DB::transaction(function () use ($validated, $parentIds) {
                $category = Category::create(collect($validated)->except('parent_ids')->all());
                $this->saveParents($category, $parentIds);
                return $category;
            });
            return response()->json($category, 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
            'parent_id' => 'nullable|exists:categories,id',
            'parent_ids' => 'sometimes|array',
            'parent_ids.*' => 'integer|distinct|exists:categories,id',
        ]);

        $parentIds = $request->has('parent_ids') || $request->exists('parent_id') ? $this->requestedParents($request) : null;
        if ($parentIds !== null && ($error = $this->parentError($parentIds, $category))) {
            return response()->json(['message' => $error], 422);
        }

        try {
            DB::transaction(function () use ($category, $validated, $parentIds) {
                $category->update(collect($validated)->except(['parent_ids', 'parent_id'])->all());
                if ($parentIds !== null) {
                    $this->saveParents($category, $parentIds);
                }
            });
            return response()->json($category);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * The parents sent from the dashboard: a parent_ids list, or the older single parent_id.
     */
    private function requestedParents(Request $request): array
    {
        $ids = $request->has('parent_ids')
            ? (array) $request->input('parent_ids')
            : array_filter([$request->input('parent_id')]);
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * A category can sit under several parents, at any depth, as long as that never makes a loop:
     * it cannot be its own parent, or sit under one of its own sub-categories. Returns the problem, if any.
     */
    private function parentError(array $parentIds, Category $category): ?string
    {
        if (in_array($category->id, $parentIds, true)) {
            return 'A category cannot be its own parent.';
        }
        $loop = array_intersect($parentIds, $category->descendantIds());
        if ($loop) {
            $names = Category::whereKey($loop)->pluck('name')->implode(', ');
            return "{$names} is inside {$category->name}, so it cannot also be its parent.";
        }
        return null;
    }

    /** Saves the parent links; parent_id keeps the first one for anything that still reads it. */
    private function saveParents(Category $category, array $parentIds): void
    {
        $category->parents()->sync($parentIds);
        $category->forceFill(['parent_id' => $parentIds[0] ?? null])->save();
    }

    /**
     * The footer's "Shop Collections" list: up to four categories or sub-categories, in order.
     * An empty list clears it (the footer then shows the biggest categories).
     */
    public function updateFooter(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'present|array|max:4',
            'ids.*' => 'integer|distinct|exists:categories,id',
        ]);

        DB::transaction(function () use ($validated) {
            Category::whereNotNull('footer_order')->update(['footer_order' => null]);
            foreach (array_values($validated['ids']) as $position => $id) {
                Category::whereKey($id)->update(['footer_order' => $position + 1]);
            }
        });

        return response()->json(Category::whereNotNull('footer_order')->orderBy('footer_order')->get());
    }

    public function destroy(Category $category)
    {
        DB::transaction(function () use ($category) {
            // Its sub-categories keep their other parents (one with none left becomes a main category)
            foreach ($category->children()->get() as $child) {
                $child->forceFill([
                    'parent_id' => $child->parents()->whereKeyNot($category->id)->value('categories.id'),
                ])->save();
            }
            $category->delete();
        });
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
