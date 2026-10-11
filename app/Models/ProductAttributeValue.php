<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAttributeValue extends Model
{
    protected $fillable = ['product_attribute_id', 'value', 'price_modifier', 'parent_id', 'image'];

    protected $casts = [
        'price_modifier' => 'decimal:2'
    ];

    public function attribute()
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    /**
     * Options of other attributes this one is only available with. A value without links
     * combines with anything; the storefront and checkout check links in both directions.
     */
    public function links()
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_attribute_value_links', 'value_id', 'linked_value_id');
    }

    // Single "depends on" link from before value links existed; no longer read
    public function parent()
    {
        return $this->belongsTo(ProductAttributeValue::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(ProductAttributeValue::class, 'parent_id');
    }
}
