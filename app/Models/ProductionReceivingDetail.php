<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionReceivingDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'receiving_id',
        'product_id',
        'used_qty',
        'waste_qty',
        'missed_qty',
        'remarks',
    ];

    public function receiving()
    {
        return $this->belongsTo(ProductionReceiving::class, 'receiving_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
