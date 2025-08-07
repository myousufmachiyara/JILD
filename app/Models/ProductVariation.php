<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'manufacturing_cost',
        'stock_quantity',
    ];

    // Parent Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Many-to-Many: Variations can have multiple attribute values
    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variation_attribute_values');
    }

    // If you have a pivot table model (one-to-many relationship)
    public function values()
    {
        return $this->hasMany(ProductVariationAttributeValue::class);
    }

    // Optional: Receivings related to this variation
    public function receivings()
    {
        return $this->hasMany(ProductionReceivingDetail::class, 'variation_id');
    }
}
