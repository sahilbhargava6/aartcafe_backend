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
        'is_wedding_featured',
        'is_wedding_selected',
        'is_bestseller',
        'is_hero_featured',
        'is_free_delivery',
        'is_festive_special',
        'is_festive_featured',
        'is_festive_selected',
        'is_festive_hidden',
        'is_active'
    ];

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            // Keep is_wedding_featured and is_wedding_special in sync
            if ($product->isDirty('is_wedding_featured')) {
                $product->is_wedding_special = (bool) $product->is_wedding_featured;
            } elseif ($product->isDirty('is_wedding_special')) {
                $product->is_wedding_featured = (bool) $product->is_wedding_special;
            }

            if ($product->isDirty(['is_festive_featured', 'is_festive_selected', 'is_festive_hidden'])) {
                // Featured and Selected combine freely; Hidden (price on request) excludes both
                if ($product->isDirty('is_festive_hidden') && $product->is_festive_hidden) {
                    $product->is_festive_featured = false;
                    $product->is_festive_selected = false;
                } elseif ($product->is_festive_featured || $product->is_festive_selected) {
                    $product->is_festive_hidden = false;
                }
                $product->is_festive_special = $product->is_festive_featured || $product->is_festive_selected;
            } elseif ($product->isDirty('is_festive_special')) {
                // The product form only has the older single "festive" checkbox
                if ($product->is_festive_special && !$product->is_festive_featured && !$product->is_festive_selected) {
                    $product->is_festive_selected = true;
                    $product->is_festive_hidden = false;
                } elseif (!$product->is_festive_special) {
                    $product->is_festive_featured = false;
                    $product->is_festive_selected = false;
                }
            }
        });
    }

    protected $casts = [
        'images' => 'array',
        'is_new_discovery' => 'boolean',
        'is_wedding_special' => 'boolean',
        'is_wedding_featured' => 'boolean',
        'is_wedding_selected' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_hero_featured' => 'boolean',
        'is_free_delivery' => 'boolean',
        'is_festive_special' => 'boolean',
        'is_festive_featured' => 'boolean',
        'is_festive_selected' => 'boolean',
        'is_festive_hidden' => 'boolean',
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
        'discount_price' => 'decimal:2'
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
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
