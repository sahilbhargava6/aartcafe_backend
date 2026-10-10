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
        'is_free_delivery',
        'is_festive_special',
        'festive_status',
        'is_active'
    ];

    public const FESTIVE_STATUSES = ['featured', 'selected', 'hidden'];

    protected static function booted(): void
    {
        // Keep the older is_festive_special flag (still used by the product form)
        // in sync with festive_status, whichever one was changed.
        static::saving(function (Product $product) {
            if ($product->isDirty('festive_status')) {
                $product->is_festive_special = in_array($product->festive_status, ['featured', 'selected'], true);
            } elseif ($product->isDirty('is_festive_special')) {
                $onFestivePage = in_array($product->festive_status, ['featured', 'selected'], true);
                if ($product->is_festive_special && !$onFestivePage) {
                    $product->festive_status = 'selected';
                } elseif (!$product->is_festive_special && $onFestivePage) {
                    $product->festive_status = null;
                }
            }
        });
    }

    protected $casts = [
        'images' => 'array',
        'is_new_discovery' => 'boolean',
        'is_wedding_special' => 'boolean',
        'is_bestseller' => 'boolean',
        'is_hero_featured' => 'boolean',
        'is_free_delivery' => 'boolean',
        'is_festive_special' => 'boolean',
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
