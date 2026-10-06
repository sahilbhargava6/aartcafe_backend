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
        'discount_price',
        'description',
        'image',
        'images',
        'is_new_discovery',
        'is_wedding_special',
        'is_bestseller',
        'is_hero_featured',
        'is_free_delivery'
    ];

    protected $casts = [
        'images' => 'array',
        'is_new_discovery' => 'boolean',
        'is_wedding_special' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_hero_featured' => 'boolean',
        'is_free_delivery' => 'boolean',
        'base_price' => 'decimal:2',
        'discount_price' => 'decimal:2'
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
