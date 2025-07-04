<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionReceiving extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'production_id',
        'challan_no',
        'receive_date',
        'received_by',
        'remarks',
    ];

    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function items()
    {
        return $this->hasMany(ProductionReceivingDetail::class, 'receiving_id');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
