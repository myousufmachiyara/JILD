<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Production;
use App\Models\ProductionReceiving;
use App\Models\ProductionReturn;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use Carbon\Carbon;

class ProductionReportController extends Controller
{
    public function productionReports(Request $request)
    {
        $tab  = $request->get('tab', 'RMI');
        $from = $request->get('from_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $to   = $request->get('to_date',   Carbon::now()->format('Y-m-d'));

        $rawIssued        = collect();
        $produced         = collect();
        $costings         = collect();
        $vendorRawBalance = collect();
        $returnReport     = collect();
        $deliveryReport   = collect();
        $orderSummary     = collect();

        // ── 1. Production Order Report ────────────────────────────────
        if ($tab === 'RMI') {
            $productions = Production::with([
                    'vendor',
                    'details.product.measurementUnit',
                    'receivings.details.product',
                    'wastageReceivings.details',
                ])
                ->whereBetween('order_date', [$from, $to])
                ->orderBy('order_date', 'desc')
                ->get();

            $rawIssued = $productions->map(function ($prod) {
                $totalRawGiven = $prod->details->sum('qty');
                $totalRawCost  = $prod->details->sum(fn($d) => $d->qty * $d->rate);
                $totalFGQty    = $prod->receivings->flatMap->details->sum('received_qty');
                $wastageQty    = $prod->wastageReceivings->flatMap->details->sum('quantity');
                $rawAtVendor   = max(0, $totalRawGiven - $wastageQty);

                // Per-product consumption breakdown
                $productBreakdown = collect();
                foreach ($prod->receivings as $rec) {
                    foreach ($rec->details as $detail) {
                        $pid  = $detail->product_id;
                        $name = $detail->product->name ?? '-';

                        if (!$productBreakdown->has($pid)) {
                            $productBreakdown->put($pid, [
                                'name'        => $name,
                                'received_qty'=> 0,
                                'expected_con'=> $detail->product->consumption ?? 0,
                            ]);
                        }
                        $existing = $productBreakdown->get($pid);
                        $existing['received_qty'] += $detail->received_qty;
                        $productBreakdown->put($pid, $existing);
                    }
                }

                // Consumption per product (raw used / qty received)
                $consumptionDetails = $productBreakdown->map(function ($item) use ($totalRawGiven) {
                    $actualCon  = $item['received_qty'] > 0
                        ? round($totalRawGiven / $item['received_qty'], 4)
                        : 0;
                    $expectedCon = $item['expected_con'];
                    $alert       = $expectedCon > 0 && $actualCon > ($expectedCon * 1.1);

                    return [
                        'name'         => $item['name'],
                        'received_qty' => $item['received_qty'],
                        'actual_con'   => $actualCon,
                        'expected_con' => $expectedCon,
                        'alert'        => $alert,
                    ];
                })->values();

                return (object)[
                    'id'                  => $prod->id,
                    'date'                => $prod->order_date,
                    'vendor'              => $prod->vendor->name ?? '-',
                    'type'                => ucfirst(str_replace('_', ' ', $prod->production_type)),
                    'total_raw_given'     => $totalRawGiven,
                    'total_raw_cost'      => $totalRawCost,
                    'total_fg_received'   => $totalFGQty,
                    'wastage_returned'    => $wastageQty,
                    'raw_at_vendor'       => $rawAtVendor,
                    'consumption_details' => $consumptionDetails,
                    'raw_details'         => $prod->details,
                ];
            });
        }

        // ── 2. Production Receiving Report ────────────────────────────
        if ($tab === 'PR') {
            $produced = ProductionReceiving::with([
                    'vendor',
                    'production',
                    'details.product.measurementUnit',
                    'details.variation',
                ])
                ->whereBetween('rec_date', [$from, $to])
                ->orderBy('rec_date', 'desc')
                ->get()
                ->flatMap(function ($rec) {
                    return $rec->details->map(fn($d) => (object)[
                        'date'       => $rec->rec_date,
                        'grn_no'     => $rec->grn_no,
                        'vendor'     => $rec->vendor->name ?? '-',
                        'production' => $rec->production_id ?? '-',
                        'item_name'  => $d->product->name ?? '-',
                        'variation'  => $d->variation->sku ?? '-',
                        'unit'       => $d->product->measurementUnit->shortcode ?? '-',
                        'qty'        => $d->received_qty,
                        'm_cost'     => $d->manufacturing_cost,
                        'total'      => $d->received_qty * $d->manufacturing_cost,
                    ]);
                });
        }

        // ── 3. Product Costing ────────────────────────────────────────
        if ($tab === 'CR') {
            $allReceivings = ProductionReceiving::with(['details.product'])
                ->whereBetween('rec_date', [$from, $to])
                ->get();

            $grouped = collect();
            foreach ($allReceivings as $rec) {
                foreach ($rec->details as $d) {
                    $pid = $d->product_id;
                    if (!$grouped->has($pid)) {
                        $grouped->put($pid, [
                            'name'       => $d->product->name ?? '-',
                            'total_qty'  => 0,
                            'total_cost' => 0,
                            'expected'   => $d->product->manufacturing_cost ?? 0,
                        ]);
                    }
                    $g = $grouped->get($pid);
                    $g['total_qty']  += $d->received_qty;
                    $g['total_cost'] += $d->received_qty * $d->manufacturing_cost;
                    $grouped->put($pid, $g);
                }
            }

            $costings = $grouped->map(fn($g) => (object)[
                'product_name' => $g['name'],
                'total_qty'    => $g['total_qty'],
                'avg_cost'     => $g['total_qty'] > 0 ? round($g['total_cost'] / $g['total_qty'], 2) : 0,
                'total_cost'   => $g['total_cost'],
                'expected_cost'=> $g['expected'],
                'variance'     => round(
                    ($g['total_qty'] > 0 ? $g['total_cost'] / $g['total_qty'] : 0) - $g['expected'],
                    2
                ),
            ])->values();
        }

        // ── 4. Vendor Raw Balance (raw at vendor warehouse) ───────────
        if ($tab === 'VRB') {
            $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();

            $vendorRawBalance = $vendors->map(function ($vendor) use ($from, $to) {
                $productions = Production::with([
                        'details.product.measurementUnit',
                        'receivings.details.product',   // ← need product.consumption
                        'wastageReceivings.details',
                    ])
                    ->where('vendor_id', $vendor->id)
                    ->where('order_date', '<=', $to)
                    ->get();

                $rawSent        = collect(); // product_id => [name, unit, qty_sent]
                $rawConsumed    = collect(); // product_id => qty consumed in FG production
                $wastageBack    = collect(); // product_id => qty returned as wastage

                // ── Raw sent to vendor ────────────────────────────────────
                foreach ($productions as $prod) {
                    foreach ($prod->details as $d) {
                        $pid  = $d->product_id;
                        $name = $d->product->name ?? '-';
                        $unit = $d->product->measurementUnit->shortcode ?? '-';

                        $existing = $rawSent->get($pid, ['name' => $name, 'unit' => $unit, 'qty_sent' => 0]);
                        $existing['qty_sent'] += (float) $d->qty;
                        $rawSent->put($pid, $existing);
                    }

                    // ── Raw consumed in FG (FG qty × product.consumption) ─
                    // Each receiving detail has a product (FG) with a consumption column
                    // consumption = how much raw (sq.ft/meters) is used per FG piece
                    foreach ($prod->receivings as $rec) {
                        foreach ($rec->details as $rd) {
                            $fgQty       = (float) $rd->received_qty;
                            $consumption = (float) ($rd->product->consumption ?? 0);

                            if ($consumption <= 0 || $fgQty <= 0) continue;

                            // The raw consumed belongs to the raw products issued for this production
                            // Apportion consumed qty across all raw products sent for this production
                            // If multiple raw products, split proportionally by qty sent
                            $totalRawForThisProd = $prod->details->sum('qty');

                            if ($totalRawForThisProd <= 0) continue;

                            $totalConsumed = $fgQty * $consumption;

                            foreach ($prod->details as $d) {
                                $pid   = $d->product_id;
                                $share = $totalRawForThisProd > 0
                                    ? ($d->qty / $totalRawForThisProd) * $totalConsumed
                                    : 0;

                                $rawConsumed->put($pid, ($rawConsumed->get($pid, 0) + $share));
                            }
                        }
                    }

                    // ── Wastage returned ──────────────────────────────────
                    foreach ($prod->wastageReceivings as $wr) {
                        foreach ($wr->details as $wd) {
                            $pid = $wd->product_id;
                            $wastageBack->put($pid, ($wastageBack->get($pid, 0) + (float) $wd->quantity));
                        }
                    }
                }

                // ── Calculate remaining at vendor ─────────────────────────
                $balance = $rawSent->map(function ($item, $pid) use ($rawConsumed, $wastageBack) {
                    $consumed  = $rawConsumed->get($pid, 0);
                    $returned  = $wastageBack->get($pid, 0);
                    $remaining = max(0, $item['qty_sent'] - $consumed - $returned);

                    return (object)[
                        'product'   => $item['name'],
                        'unit'      => $item['unit'],
                        'sent'      => $item['qty_sent'],
                        'consumed'  => round($consumed, 4),
                        'returned'  => $returned,
                        'remaining' => round($remaining, 4),
                    ];
                })->values()->filter(fn($r) => $r->remaining > 0);

                return (object)[
                    'vendor'  => $vendor->name,
                    'balance' => $balance,
                    'total'   => $balance->count(),
                ];
            })->filter(fn($v) => $v->total > 0)->values();
        }

        // ── 5. Production Return Report ───────────────────────────────
        if ($tab === 'RTN') {
            $returns = ProductionReturn::with([
                    'vendor',
                    'items.product',
                    'items.variation',
                    'items.unit',
                ])
                ->whereBetween('return_date', [$from, $to])
                ->orderBy('return_date', 'desc')
                ->get();

            $returnReport = $returns->flatMap(function ($ret) {
                return $ret->items->map(fn($item) => (object)[
                    'date'       => $ret->return_date,
                    'return_id'  => $ret->id,
                    'vendor'     => $ret->vendor->name ?? '-',
                    'item_name'  => $item->product->name ?? '-',
                    'variation'  => $item->variation->sku ?? '-',
                    'production' => $item->production_id ?? '-',
                    'qty'        => $item->quantity,
                    'unit'       => $item->unit->shortcode ?? '-',
                    'rate'       => $item->price,
                    'total'      => $item->quantity * $item->price,
                ]);
            });
        }

        // ── 6. Delivery Time Tracking ─────────────────────────────────
        if ($tab === 'DLV') {
            $productions = Production::with(['vendor', 'receivings'])
                ->where('order_date', '>=', $from)
                ->where('order_date', '<=', $to)
                ->orderBy('order_date', 'desc')
                ->get();

            $deliveryReport = $productions->map(function ($prod) {
                $firstReceiving = $prod->receivings->sortBy('rec_date')->first();
                $lastReceiving  = $prod->receivings->sortByDesc('rec_date')->first();
                $orderDate      = Carbon::parse($prod->order_date);
                $firstRecDate   = $firstReceiving ? Carbon::parse($firstReceiving->rec_date) : null;
                $lastRecDate    = $lastReceiving  ? Carbon::parse($lastReceiving->rec_date)  : null;

                $totalFG        = $prod->receivings->flatMap->details->sum('received_qty');
                $totalRaw       = $prod->details->sum('qty');
                $isComplete     = $prod->receivings->count() > 0 && $totalFG > 0;

                return (object)[
                    'production_id'    => $prod->id,
                    'vendor'           => $prod->vendor->name ?? '-',
                    'order_date'       => $prod->order_date,
                    'first_receiving'  => $firstRecDate?->toDateString() ?? 'Pending',
                    'last_receiving'   => $lastRecDate?->toDateString()  ?? 'Pending',
                    'days_to_first'    => $firstRecDate ? $orderDate->diffInDays($firstRecDate) : null,
                    'days_to_last'     => $lastRecDate  ? $orderDate->diffInDays($lastRecDate)  : null,
                    'total_raw'        => $totalRaw,
                    'total_fg'         => $totalFG,
                    'receiving_count'  => $prod->receivings->count(),
                    'status'           => $isComplete ? 'Received' : 'Pending',
                ];
            });
        }

        // ── 7. Production Order Summary (per vendor) ──────────────────
        if ($tab === 'SUM') {
            $productions = Production::with(['vendor', 'details', 'receivings.details'])
                ->whereBetween('order_date', [$from, $to])
                ->get();

            $orderSummary = $productions->groupBy('vendor_id')->map(function ($prods, $vendorId) {
                $vendor     = $prods->first()->vendor->name ?? '-';
                $totalRaw   = $prods->flatMap->details->sum('qty');
                $totalCost  = $prods->flatMap->details->sum(fn($d) => $d->qty * $d->rate);
                $totalFG    = $prods->flatMap->receivings->flatMap->details->sum('received_qty');
                $totalMfg   = $prods->flatMap->receivings->flatMap->details
                    ->sum(fn($d) => $d->received_qty * $d->manufacturing_cost);
                $orderCount = $prods->count();

                return (object)[
                    'vendor'      => $vendor,
                    'orders'      => $orderCount,
                    'total_raw'   => $totalRaw,
                    'raw_cost'    => $totalCost,
                    'total_fg'    => $totalFG,
                    'mfg_cost'    => $totalMfg,
                    'avg_con'     => $totalFG > 0 ? round($totalRaw / $totalFG, 4) : 0,
                ];
            })->values();
        }

        return view('reports.production_reports', compact(
            'tab', 'from', 'to',
            'rawIssued', 'produced', 'costings',
            'vendorRawBalance', 'returnReport', 'deliveryReport', 'orderSummary'
        ));
    }
}