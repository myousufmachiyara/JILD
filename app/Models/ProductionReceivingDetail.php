<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionReceivingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_receiving_id',
        'product_id',
        'production_id',
        'variation_id',
        'manufacturing_cost',
        'received_qty',
        'total_unit_cost',
        'total',
        'remarks',
    ];

    protected $casts = [
        'manufacturing_cost' => 'float',
        'received_qty' => 'float',
        'total_unit_cost' => 'float',
        'total' => 'float',
    ];

    public function receiving()
    {
        return $this->belongsTo(ProductionReceiving::class, 'production_receiving_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variation()
    {
        return $this->belongsTo(ProductVariation::class);
    }
}
