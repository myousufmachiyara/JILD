<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    protected $fillable = ['vendor_id', 'return_date', 'reference_no', 'remarks', 'total_amount', 'net_amount'];

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }
}
