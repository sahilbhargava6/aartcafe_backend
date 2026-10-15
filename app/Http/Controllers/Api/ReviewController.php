<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        return response()->json(Review::with('product')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required_without:product_ids|nullable|exists:products,id',
            'product_ids' => 'sometimes|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'reviewer_name' => 'required|string|max:255',
            'review_text' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'is_approved' => 'nullable|boolean'
        ]);

        $productIds = $request->has('product_ids')
            ? array_values(array_unique(array_map('intval', (array) $request->input('product_ids'))))
            : [intval($validated['product_id'])];

        $reviews = [];
        \Illuminate\Support\Facades\DB::transaction(function () use ($productIds, $validated, &$reviews) {
            foreach ($productIds as $pId) {
                $reviews[] = Review::create([
                    'product_id' => $pId,
                    'reviewer_name' => $validated['reviewer_name'],
                    'review_text' => $validated['review_text'],
                    'rating' => $validated['rating'],
                    'is_approved' => $validated['is_approved'] ?? true,
                ]);
            }
        });

        return response()->json(count($reviews) === 1 ? $reviews[0] : $reviews, 201);
    }

    public function show(Review $review)
    {
        return response()->json($review->load('product'));
    }

    public function update(Request $request, Review $review)
    {
        $validated = $request->validate([
            'product_id' => 'sometimes|required|exists:products,id',
            'reviewer_name' => 'sometimes|required|string|max:255',
            'review_text' => 'sometimes|required|string',
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'is_approved' => 'nullable|boolean'
        ]);

        $review->update($validated);
        return response()->json($review);
    }

    public function destroy(Review $review)
    {
        $review->delete();
        return response()->json(['message' => 'Review deleted successfully']);
    }
}
