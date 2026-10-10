<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttributeValue extends Model
{
    protected $fillable = ['product_attribute_id', 'value', 'price_modifier', 'parent_id'];

    protected $casts = [
        'price_modifier' => 'decimal:2'
    ];

    public function attribute()
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    public function parent()
    {
        return $this->belongsTo(ProductAttributeValue::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ProductAttributeValue::class, 'parent_id');
    }
}
