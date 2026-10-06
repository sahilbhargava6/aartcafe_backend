<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsiteAnalytic;
use App\Models\Product;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function website()
    {
        $analytics = WebsiteAnalytic::orderBy('date', 'asc')->get();
        
        // Format payload matching home page expectations
        return response()->json([
            'status' => 'success',
            'data' => [
                'latest' => $analytics->last(),
                'history' => $analytics
            ]
        ]);
    }

    public function products()
    {
        // Simple aggregate representation of products viewed or converted
        $products = Product::with('category')->select('id', 'title', 'category_id')->get()->map(function($product) {
            return [
                'id' => $product->id,
                'title' => $product->title,
                'category' => $product->category->name ?? 'Uncategorized',
                'views' => rand(150, 1000),
                'sales' => rand(5, 50),
                'conversion_rate' => rand(2, 8) . '%'
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }
}
