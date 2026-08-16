<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'product_id',
        'reviewer_name',
        'review_text',
        'rating',
        'is_approved'
    ];

    protected $casts = [
        'is_approved' => 'boolean'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
