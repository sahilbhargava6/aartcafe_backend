<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'base_price',
        'description',
        'image',
        'is_new_discovery',
        'is_wedding_special',
        'is_bestseller'
    ];

    protected $casts = [
        'is_new_discovery' => 'boolean',
        'is_wedding_special' => 'boolean',
        'is_bestseller' => 'boolean',
        'base_price' => 'decimal:2'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function attributes()
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
