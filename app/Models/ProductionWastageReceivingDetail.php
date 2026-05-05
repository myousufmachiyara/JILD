<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionWastageReceivingDetail extends Model
{
    protected $fillable = [
        'wastage_receiving_id', 'product_id', 'variation_id', 'quantity', 'unit_id', 'remarks',
    ];

    public function product()   { return $this->belongsTo(Product::class); }
    public function variation() { return $this->belongsTo(ProductVariation::class); }
    public function unit()      { return $this->belongsTo(MeasurementUnit::class, 'unit_id'); }
}