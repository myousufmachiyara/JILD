<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'item_id',
        'bundle',
        'quantity',
        'unit',
        'price',
        'remarks',
    ];

    public function invoice()
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'item_id');
    }
    
    public function unit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'unit');
    }
}
