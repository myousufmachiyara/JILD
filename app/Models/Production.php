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
        'attachments',
        'created_by',
    ];

    protected $casts = ['attachments' => 'array'];

    public function vendor()    { return $this->belongsTo(ChartOfAccounts::class, 'vendor_id'); }
    public function category()  { return $this->belongsTo(ProductCategory::class, 'category_id'); }
    public function details()   { return $this->hasMany(ProductionDetail::class); }
    public function receivings(){ return $this->hasMany(ProductionReceiving::class); }
    public function wastageReceivings() { return $this->hasMany(ProductionWastageReceiving::class); }

    // ── Computed stats ────────────────────────────────────────────────

    /** Total raw qty sent to production */
    public function getTotalRawGivenAttribute(): float
    {
        return (float) $this->details->sum('qty');
    }

    /** Total finished goods received back */
    public function getTotalFinishedReceivedAttribute(): float
    {
        return (float) $this->receivings->flatMap->details->sum('received_qty');
    }

    /** Total wastage raw returned */
    public function getTotalWastageReturnedAttribute(): float
    {
        return (float) $this->wastageReceivings->flatMap->details->sum('quantity');
    }

    /** Raw still at manufacturer = given - (used in FG + returned as wastage) */
    public function getRawAtManufacturerAttribute(): float
    {
        $totalRaw = $this->total_raw_given;
        $wastage  = $this->total_wastage_returned;
        // We don't know exactly how much raw was consumed per FG unit unless manufacturing_cost tracks it
        // So: raw at manufacturer = given - wastage returned
        return max(0, $totalRaw - $wastage);
    }

    /** Consumption ratio = FG received / raw given */
    public function getConsumptionRatioAttribute(): float
    {
        $raw = $this->total_raw_given;
        $fg  = $this->total_finished_received;
        return $raw > 0 ? round($fg / $raw, 4) : 0;
    }
}