<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentVoucher extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'date',
        'ac_dr_sid',
        'ac_cr_sid',
        'amount',
        'remarks',
        'attachments'
    ];

    protected $casts = [
        'attachments' => 'array',
    ];

    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'ac_dr_sid', 'id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'ac_cr_sid', 'id');
    }
}
