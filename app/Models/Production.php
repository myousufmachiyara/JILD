<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Production extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'category_id',
        'order_date',
        'production_type',
        'remarks',
        'created_by',
    ];

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class); // if category table exists
    }

    public function details()
    {
        return $this->hasMany(ProductionDetail::class);
    }

    public function receivings()
    {
        return $this->hasMany(ProductionReceiving::class);
    }

}
