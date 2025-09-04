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
        'opening_stock',
        'selling_price',
        'consumption',
        'reorder_level',
        'max_stock_level',
        'minimum_order_qty',
        'measurement_unit',
        'item_type',    // fg, raw, service
        'is_active',
    ];

    protected static function booted()
    {
        static::creating(function ($product) {
            // Only generate FG barcode if it's FG type and empty
            if ($product->item_type === 'fg' && empty($product->barcode)) {
                $product->barcode = generateFgBarcode();
            } 
            // For raw/service types, you can keep your existing prefix logic
            elseif (empty($product->barcode)) {
                $prefix = match($product->item_type) {
                    'raw' => 'RAW-',
                    'service' => 'SRV-',
                    default => 'PRD-',
                };
                $lastId = Product::max('id') + 1;
                $product->barcode = $prefix . str_pad($lastId, 6, '0', STR_PAD_LEFT);
            }
        });

        // Remove product-level barcode if FG variations exist
        static::created(function ($product) {
            if ($product->item_type === 'fg' && $product->variations()->exists()) {
                $product->updateQuietly(['barcode' => null]);
            }
        });
    }

    /* ----------------- Relationships ----------------- */

    // Belongs to category
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    // Has many variations
    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    // Has many images
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    // Belongs to measurement unit
    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit');
    }
}
