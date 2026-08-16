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
            'product_id' => 'required|exists:products,id',
            'reviewer_name' => 'required|string|max:255',
            'review_text' => 'required|string',
            'rating' => 'required|integer|min:1|max:5',
            'is_approved' => 'nullable|boolean'
        ]);

        $review = Review::create($validated);
        return response()->json($review, 201);
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
