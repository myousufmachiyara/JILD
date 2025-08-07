<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'sku',
        'barcode',
        'description',
        'manufacturing_cost',
        'measurement_unit',
        'item_type',
        'opening_stock',
        'selling_price',
        'consumption',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }
    
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
