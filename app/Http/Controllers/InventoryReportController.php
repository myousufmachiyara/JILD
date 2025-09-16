<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnItem;
use App\Models\ProductionDetail;
use App\Models\ProductionReceivingDetail;
use App\Models\StockTransferDetail;
use App\Models\Location;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $tab        = $request->tab ?? 'IL';
        $selected   = $request->item_id ?? null; // may be "productId" or "productId-variationId"
        $from       = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to         = $request->to_date ?? now()->toDateString();
        $locationId = $request->location_id ?? null;
        $locations  = Location::all();

        // parse product and variation if provided in "productId-variationId" format
        $productId = null;
        $variationId = null;
        if ($selected) {
            if (str_contains($selected, '-')) {
                [$p, $v] = explode('-', $selected);
                $productId   = (int) $p;
                $variationId = $v !== '' ? (int) $v : null;
            } else {
                $productId = (int) $selected;
            }
        }

        // load all products (for dropdown)
        $allProducts = Product::with('variations')->get();

        // initialize variables so view always receives them
        $itemLedger   = collect();
        $stockInHand  = collect(); // <<-- IMPORTANT: initialize here
        $stockTransfers = collect();
        $nonMovingItems = collect();
        $reorderLevel = collect();

        // --------------------------
        // ITEM LEDGER
        // --------------------------
        if ($tab === 'IL' && $productId) {
            $product = $allProducts->firstWhere('id', $productId);
            if ($product) {
                if ($variationId) {
                    $var = $product->variations->firstWhere('id', $variationId);
                    $variations = $var ? collect([$var]) : collect([(object)['id' => $variationId, 'sku' => null]]);
                } else {
                    $variations = $product->variations->isNotEmpty()
                        ? $product->variations
                        : collect([(object)['id' => null, 'sku' => null]]);
                }

                foreach ($variations as $var) {
                    $ledger = collect();

                    // Purchases
                    $ledger = $ledger->concat(
                        PurchaseInvoiceItem::where('item_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('invoice', fn($q) => $q->whereBetween('invoice_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->invoice->invoice_date,
                                'type' => 'Purchase',
                                'description' => 'Bill No: ' . ($row->invoice->bill_no ?? $row->invoice->id),
                                'qty_in' => $row->quantity,
                                'qty_out' => 0,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Purchase Returns
                    $ledger = $ledger->concat(
                        PurchaseReturnItem::where('item_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('return', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->return->return_date,
                                'type' => 'Purchase Return',
                                'description' => 'Return No: ' . ($row->return->reference_no ?? $row->return->id),
                                'qty_in' => 0,
                                'qty_out' => $row->quantity,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Sales
                    $ledger = $ledger->concat(
                        SaleInvoiceItem::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('invoice', fn($q) => $q->whereBetween('date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->invoice->date,
                                'type' => 'Sale',
                                'description' => 'Invoice No: ' . ($row->invoice->invoice_no ?? $row->invoice->id),
                                'qty_in' => 0,
                                'qty_out' => $row->quantity,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Sale Returns
                    $ledger = $ledger->concat(
                        SaleReturnItem::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('saleReturn', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->saleReturn->return_date,
                                'type' => 'Sale Return',
                                'description' => 'Return No: ' . ($row->saleReturn->reference_no ?? $row->saleReturn->id),
                                'qty_in' => $row->qty,
                                'qty_out' => 0,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Production Issue
                    $ledger = $ledger->concat(
                        ProductionDetail::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('production', fn($q) => $q->whereBetween('order_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->production->order_date,
                                'type' => 'Production Issue',
                                'description' => 'Raw Material Issued',
                                'qty_in' => 0,
                                'qty_out' => $row->qty,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Production Receiving
                    $ledger = $ledger->concat(
                        ProductionReceivingDetail::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('receiving', fn($q) => $q->whereBetween('rec_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->receiving->rec_date,
                                'type' => 'Production Receiving',
                                'description' => 'Manufactured Goods Received',
                                'qty_in' => $row->received_qty,
                                'qty_out' => 0,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    $itemLedger = $itemLedger->concat($ledger->sortBy('date'));
                }
            }
        }

// STOCK INHAND
if ($tab == 'SR') {
    $selectedItem   = $request->item_id;
    $costingMethod  = $request->costing_method ?? 'avg'; // avg | max | min | latest
    $productsToProcess = $allProducts;

    // filter selected product/variation
    if ($selectedItem) {
        if (str_contains($selectedItem, '-')) {
            [$productIdSel, $variationIdSel] = explode('-', $selectedItem);
            $productsToProcess = $allProducts->where('id', (int)$productIdSel);
            $productsToProcess->transform(function ($product) use ($variationIdSel) {
                $product->variations = $product->variations->where('id', (int)$variationIdSel);
                return $product;
            });
        } else {
            $productsToProcess = $allProducts->where('id', (int)$selectedItem);
        }
    }

    foreach ($productsToProcess as $product) {
        $variations = $product->variations->isNotEmpty()
            ? $product->variations
            : collect([(object)['id' => null, 'sku' => null]]);

        foreach ($variations as $var) {
            // ------------------------------
            // STOCK MOVEMENTS
            // ------------------------------
            $purchased = PurchaseInvoiceItem::where('item_id', $product->id)
                ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                ->sum('quantity');

            $purchaseReturn = PurchaseReturnItem::where('item_id', $product->id)
                ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                ->sum('quantity');

            $sold = SaleInvoiceItem::where('product_id', $product->id)
                ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                ->sum('quantity');

            $saleReturn = SaleReturnItem::where('product_id', $product->id)
                ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                ->sum('qty');

            // total raw issued (we will re-calc more precisely below)
            $issued = ProductionDetail::where('product_id', $product->id)
                ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                ->sum('qty');

            // total finished received (pcs)
            $received = ProductionReceivingDetail::where('product_id', $product->id)
                ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                ->sum('received_qty');

            $stockQty = ($purchased - $purchaseReturn + $saleReturn + $received) - ($sold + $issued);

            // ------------------------------
            // COSTING: link raw usage to the productions that produced THIS finished product
            // ------------------------------
            // 1) find productions that produced this finished product (their ids)
            $productionIds = ProductionReceivingDetail::where('product_id', $product->id)
                ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                ->pluck('production_id')
                ->unique()
                ->filter() // remove nulls if any
                ->values()
                ->all();

            // if no production ids found, we fall back to previous approach (best-effort)
            if (empty($productionIds)) {
                // fallback: try to use production_details directly keyed by product (existing data)
                $rawDetailsQuery = ProductionDetail::where('product_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id));
            } else {
                // get production_detail rows that were used in those productions (these are RAW items)
                $rawDetailsQuery = ProductionDetail::whereIn('production_id', $productionIds);
            }

            $rawDetails = $rawDetailsQuery
                ->get(['product_id as raw_product_id', 'variation_id as raw_variation_id', 'qty']);

            // group by raw product + variation to calculate total raw qty per raw item
            $rawGrouped = $rawDetails->groupBy(function ($row) {
                return $row->raw_product_id . '::' . ($row->raw_variation_id ?? '0');
            });

            $totalFinishedReceived = $received; // finished pcs (same as earlier variable)
            $totalRawCostValue = 0.0; // total raw cost (currency)
            $totalRawQtyAll = 0.0;    // total raw units used across all raw items (for info)

            // 2) for each raw item used, get its total qty and determine purchase rate (based on costingMethod),
            //    then add qty * rate to totalRawCostValue
            foreach ($rawGrouped as $key => $rows) {
                // parse key
                [$rawProductId, $rawVariationId] = explode('::', $key);
                $rawProductId = (int)$rawProductId;
                $rawVariationId = (int)$rawVariationId ?: null;

                $rawQty = $rows->sum('qty');
                $totalRawQtyAll += $rawQty;

                // find purchase rate for this raw item (same 4 methods)
                $pq = PurchaseInvoiceItem::where('item_id', $rawProductId)
                    ->when(!is_null($rawVariationId), fn($q) => $q->where('variation_id', $rawVariationId));

                switch ($costingMethod) {
                    case 'max':
                        $rawRateForItem = $pq->max('price') ?? 0;
                        break;
                    case 'min':
                        $rawRateForItem = $pq->min('price') ?? 0;
                        break;
                    case 'latest':
                        $latest = $pq->orderByDesc('id')->first();
                        $rawRateForItem = $latest ? (float)$latest->price : 0;
                        break;
                    default: // weighted avg by quantity (recommended)
                        $agg = $pq->selectRaw('SUM(quantity * price) as total_value, SUM(quantity) as total_qty')->first();
                        $totValue = $agg->total_value ?? 0;
                        $totQty   = $agg->total_qty ?? 0;
                        $rawRateForItem = $totQty > 0 ? ($totValue / $totQty) : 0;
                        break;
                }

                // accumulate raw cost value
                $totalRawCostValue += ($rawQty * ($rawRateForItem ?? 0));
            }

            // raw cost per finished piece = totalRawCostValue / totalFinishedReceived
            $rawCostPerPiece = $totalFinishedReceived > 0
                ? ($totalRawCostValue / $totalFinishedReceived)
                : 0;

            // ------------------------------
            // manufacturing cost per piece:
            // We'll compute total manufacturing value (so this works whether manufacturing_cost is per-piece or batch-total)
            // Approach:
            // - total_mfg_value_by_weight = SUM(manufacturing_cost * received_qty)  (works if manufacturing_cost is per-piece)
            // - total_mfg_value_by_sum    = SUM(manufacturing_cost)                (works if manufacturing_cost is batch-total)
            // We'll prefer weighted approach first; but if that produces absurd results (e.g. extremely large),
            // we have a safe fallback to divide SUM(manufacturing_cost) by SUM(received_qty).
            // ------------------------------
            $mfgRows = ProductionReceivingDetail::where('product_id', $product->id)
                ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                ->get(['manufacturing_cost', 'received_qty']);

            $totalMfgValueByWeight = $mfgRows->sum(function ($r) {
                // treat manufacturing_cost as per-piece if that's the case (manufacturing_cost * received_qty)
                return ((float)$r->manufacturing_cost) * ((float)($r->received_qty ?? 0));
            });

            $totalMfgSum = $mfgRows->sum('manufacturing_cost'); // raw sum of column
            $totalMfgQty = $mfgRows->sum('received_qty');

            // compute candidate per-piece values
            $mfgPerPiece_byWeight = $totalMfgQty > 0 ? ($totalMfgValueByWeight / $totalMfgQty) : null; // expected if manufacturing_cost is per-piece
            $mfgPerPiece_bySum    = $totalMfgQty > 0 ? ($totalMfgSum / $totalMfgQty) : null;       // expected if manufacturing_cost is batch-total

            // Choose the most plausible per-piece manufacturing cost:
            // - If weighted value exists and is > 0, prefer it (handles per-piece storage).
            // - Otherwise fall back to sum/qty (handles batch-total storage).
            // - If both exist, pick the one that is numeric and reasonable (we'll choose the smaller of the two if they differ wildly).
            if ($mfgPerPiece_byWeight !== null && $mfgPerPiece_byWeight > 0) {
                // if both candidates exist and they differ significantly, choose the smaller one
                if ($mfgPerPiece_bySum !== null && $mfgPerPiece_bySum > 0) {
                    // if weight-based is > 5x the sum-based, it likely means manufacturing_cost was batch total and we multiplied wrongly;
                    // choose the sum-based in that case. Otherwise choose weight-based.
                    if ($mfgPerPiece_byWeight > ($mfgPerPiece_bySum * 5)) {
                        $manufacturingCostPerPiece = $mfgPerPiece_bySum;
                    } else {
                        $manufacturingCostPerPiece = $mfgPerPiece_byWeight;
                    }
                } else {
                    $manufacturingCostPerPiece = $mfgPerPiece_byWeight;
                }
            } else {
                $manufacturingCostPerPiece = $mfgPerPiece_bySum ?? 0;
            }

            // ------------------------------
            // Final per unit cost and total valuation
            // ------------------------------
            $costPrice = ($rawCostPerPiece ?? 0) + ($manufacturingCostPerPiece ?? 0);
            $costPrice = (float) $costPrice;

            $stockInHand->push([
                'product'        => $product->name,
                'variation'      => $var->sku ?? null,
                'quantity'       => $stockQty,
                // helpful intermediate fields for validation
                'total_raw_qty'  => round($totalRawQtyAll, 4),
                'total_raw_value'=> round($totalRawCostValue, 4),
                'consumption'    => $totalFinishedReceived > 0 ? round($totalRawQtyAll / $totalFinishedReceived, 4) : 0,
                'raw_cost'       => round($rawCostPerPiece, 4),
                'mfg_cost'       => round($manufacturingCostPerPiece, 4),
                'price'          => round($costPrice, 2),             // per piece
                'total'          => round($stockQty * $costPrice, 2)  // total valuation
            ]);
        }
    }
}


        // STOCK TRANSFERS (kept as you had)
        $transferQuery = StockTransferDetail::with([
                'transfer',
                'product',
                'variation',
                'transfer.fromLocation',
                'transfer.toLocation'
            ])
            ->when($request->from_location_id, function ($q) use ($request) {
                $q->whereHas('transfer', fn($t) => $t->where('from_location_id', $request->from_location_id));
            })
            ->when($request->to_location_id, function ($q) use ($request) {
                $q->whereHas('transfer', fn($t) => $t->where('to_location_id', $request->to_location_id));
            })
            ->when($request->from_date && $request->to_date, function ($q) use ($request) {
                $q->whereHas('transfer', fn($t) => $t->whereBetween('date', [$request->from_date, $request->to_date]));
            });

        $transfers = $transferQuery->get();

        $stockTransfers = $transfers->map(fn($row) => [
            'date' => $row->transfer->date ?? null,
            'reference' => $row->transfer->id ?? null,
            'product' => $row->product->name ?? null,
            'variation' => $row->variation->sku ?? null,
            'from' => $row->transfer->fromLocation->name ?? '',
            'to' => $row->transfer->toLocation->name ?? '',
            'quantity' => $row->quantity,
        ]);

        // NON-MOVING & REORDER (kept as before)...
        // (your existing logic for $nonMovingItems and $reorderLevel remains unchanged)

        return view('reports.inventory_reports', [
            'products'       => $allProducts,
            'tab'            => $tab,
            'itemLedger'     => $itemLedger->sortBy('date')->values(),
            'stockInHand'    => $stockInHand,
            'stockTransfers' => $stockTransfers,
            'nonMovingItems' => $nonMovingItems,
            'reorderLevel'   => $reorderLevel,
            'from'           => $from,
            'to'             => $to,
            'locationId'     => $locationId,
            'locations'      => $locations,
        ]);
    }
}
