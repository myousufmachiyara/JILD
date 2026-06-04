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

    // ── Relationships ─────────────────────────────────────────────────

    public function vendor()
    {
        return $this->belongsTo(ChartOfAccounts::class, 'vendor_id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function details()
    {
        return $this->hasMany(ProductionDetail::class);
    }

    public function receivings()
    {
        return $this->hasMany(ProductionReceiving::class);
    }

    public function wastageReceivings()
    {
        return $this->hasMany(ProductionWastageReceiving::class, 'production_id');
    }

    public function returns()
    {
        return $this->hasMany(ProductionReturnItem::class, 'production_id');
    }

    // ── Computed stats ────────────────────────────────────────────────

    /** Total raw qty sent to vendor */
    public function getTotalRawGivenAttribute(): float
    {
        return (float) $this->details->sum('qty');
    }

    /** Total raw cost sent to vendor */
    public function getTotalRawCostAttribute(): float
    {
        return (float) $this->details->sum(fn($d) => $d->qty * $d->rate);
    }

    /** Total finished goods received back */
    public function getTotalFinishedReceivedAttribute(): float
    {
        return (float) $this->receivings->flatMap->details->sum('received_qty');
    }

    /** Total wastage raw returned by vendor */
    public function getTotalWastageReturnedAttribute(): float
    {
        return (float) $this->wastageReceivings->flatMap->details->sum('quantity');
    }

    /**
     * Expected raw consumed = sum(FG received qty × product.consumption)
     * Uses the consumption field set on each FG product.
     * This is what should have been consumed based on product baseline.
     */
    public function getExpectedConsumedAttribute(): float
    {
        $total = 0;
        foreach ($this->receivings as $rec) {
            foreach ($rec->details as $d) {
                $total += (float) $d->received_qty * (float) ($d->product->consumption ?? 0);
            }
        }
        return round($total, 4);
    }

    /**
     * Actual consumption per FG piece = total raw sent / total FG received
     */
    public function getActualConsumptionPerPcAttribute(): float
    {
        $fg = $this->total_finished_received;
        return $fg > 0 ? round($this->total_raw_given / $fg, 4) : 0;
    }

    /**
     * Raw still at vendor (expected basis):
     *   Sent − Expected Consumed − Wastage Returned
     * This is the most useful figure for tracking accountability.
     */
    public function getRawAtVendorExpectedAttribute(): float
    {
        return max(0, $this->total_raw_given - $this->expected_consumed - $this->total_wastage_returned);
    }

    /**
     * Raw still at vendor (actual basis):
     *   Sent − Actual Consumed − Wastage Returned
     */
    public function getRawAtVendorActualAttribute(): float
    {
        $actualConsumed = $this->actual_consumption_per_pc * $this->total_finished_received;
        return max(0, $this->total_raw_given - $actualConsumed - $this->total_wastage_returned);
    }

    /**
     * Average manufacturing cost per FG piece from all receivings
     */
    public function getAvgCmtCostAttribute(): float
    {
        $totalQty  = $this->total_finished_received;
        $totalCost = (float) $this->receivings->flatMap->details->sum(
            fn($d) => $d->received_qty * $d->manufacturing_cost
        );
        return $totalQty > 0 ? round($totalCost / $totalQty, 2) : 0;
    }

    /**
     * Average total product cost per FG piece = raw cost per pc + avg CMT cost
     * This feeds into PnL as the product's landed cost.
     */
    public function getAvgProductCostAttribute(): float
    {
        $fg = $this->total_finished_received;
        if ($fg <= 0) return 0;

        $rawCostPerPc = $this->total_raw_cost / $fg;
        return round($rawCostPerPc + $this->avg_cmt_cost, 2);
    }

    /**
     * Consumption variance % = (actual - expected) / expected × 100
     * Positive = over-consumed, Negative = under-consumed
     */
    public function getConsumptionVariancePctAttribute(): ?float
    {
        $expected = $this->actual_consumption_per_pc > 0
            ? $this->receivings->flatMap->details->first()?->product?->consumption ?? 0
            : 0;

        // Use expected_consumed / FG for a per-pc expected figure
        $fg = $this->total_finished_received;
        if ($fg <= 0) return null;

        $expectedPerPc = $fg > 0 ? $this->expected_consumed / $fg : 0;
        if ($expectedPerPc <= 0) return null;

        $actual = $this->actual_consumption_per_pc;
        return round(($actual - $expectedPerPc) / $expectedPerPc * 100, 1);
    }
}