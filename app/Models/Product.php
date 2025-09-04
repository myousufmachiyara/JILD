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
            if (empty($product->barcode)) {
                $lastId = Product::max('id') + 1;

                // Prefix based on type
                switch ($product->item_type) {
                    case 'raw':
                        $prefix = 'RAW-';
                        break;
                    case 'service':
                        $prefix = 'SRV-';
                        break;
                    case 'fg': // FG products don’t use product-level barcode, only variations
                        $prefix = null;
                        break;
                    default:
                        $prefix = 'PRD-';
                }

                if ($prefix) {
                    $product->barcode = $prefix . str_pad($lastId, 6, '0', STR_PAD_LEFT);
                }
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
