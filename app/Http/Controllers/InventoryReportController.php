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
        $selected   = $request->item_id ?? null;
        $from       = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to         = $request->to_date ?? now()->toDateString();
        $locationId = $request->location_id ?? null;
        $locations  = Location::all();

        // Parse "productId" or "productId-variationId"
        $productId   = null;
        $variationId = null;
        if ($selected) {
            $parts = explode('-', $selected, 2);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $productId   = (int) $parts[0];
                $variationId = (int) $parts[1];
            } else {
                $productId = (int) $selected;
            }
        }

        $allProducts = Product::with('variations')->get();

        $itemLedger     = collect();
        $stockInHand    = collect();
        $stockTransfers = collect();
        $nonMovingItems = collect();
        $reorderLevel   = collect();

        // ── Helper: get purchase cost for a product using costing method ──────
        $getPurchaseCost = function (int $itemId, string $method, ?int $varId = null) {
            // Build query respecting variation
            $hasVarSpecific = $varId
                ? PurchaseInvoiceItem::where('item_id', $itemId)->where('variation_id', $varId)->exists()
                : false;

            if ($varId && $hasVarSpecific) {
                $pq = PurchaseInvoiceItem::where('item_id', $itemId)->where('variation_id', $varId);
            } elseif ($varId && !$hasVarSpecific) {
                $pq = PurchaseInvoiceItem::where('item_id', $itemId)->whereNull('variation_id');
                if (!$pq->exists()) return 0;
            } else {
                $pq = PurchaseInvoiceItem::where('item_id', $itemId);
            }

            return match ($method) {
                'max'    => (float) ($pq->max('price') ?? 0),
                'min'    => (float) ($pq->min('price') ?? 0),
                'latest' => (float) (optional($pq->latest('id')->first())->price ?? 0),
                default  => (function () use ($pq) {
                    $agg = $pq->selectRaw('SUM(quantity * price) as v, SUM(quantity) as q')->first();
                    return ($agg && $agg->q > 0) ? ($agg->v / $agg->q) : 0;
                })(),
            };
        };

        // ────────────────────────────────────────────────────────────────────
        // ITEM LEDGER
        // ────────────────────────────────────────────────────────────────────
        if ($tab === 'IL' && $productId) {
            $product = $allProducts->firstWhere('id', $productId);

            if ($product) {
                if ($variationId) {
                    $var        = $product->variations->firstWhere('id', $variationId);
                    $variations = $var
                        ? collect([$var])
                        : collect([(object)['id' => $variationId, 'sku' => null]]);
                } else {
                    $variations = $product->variations->isNotEmpty()
                        ? $product->variations
                        : collect([(object)['id' => null, 'sku' => null]]);
                }

                foreach ($variations as $var) {
                    $ledger = collect();

                    // Purchases
                    $ledger = $ledger->concat(
                        PurchaseInvoiceItem::with('invoice')
                            ->where('item_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('invoice', fn($q) => $q->whereBetween('invoice_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date'        => $row->invoice->invoice_date,
                                'type'        => 'Purchase',
                                'description' => 'Invoice: ' . ($row->invoice->invoice_no ?? $row->invoice->id)
                                    . ($row->invoice->bill_no ? ' | Bill: ' . $row->invoice->bill_no : ''),
                                'qty_in'    => $row->quantity,
                                'qty_out'   => 0,
                                'rate'      => $row->price,
                                'product'   => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Purchase Returns
                    $ledger = $ledger->concat(
                        PurchaseReturnItem::with('return')
                            ->where('item_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('return', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date'        => $row->return->return_date,
                                'type'        => 'Purchase Return',
                                'description' => 'Return: ' . ($row->return->reference_no ?? $row->return->id),
                                'qty_in'    => 0,
                                'qty_out'   => $row->quantity,
                                'rate'      => $row->price ?? 0,
                                'product'   => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Sales
                    $ledger = $ledger->concat(
                        SaleInvoiceItem::with('invoice')
                            ->where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('invoice', fn($q) => $q->whereBetween('date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date'        => $row->invoice->date,
                                'type'        => 'Sale',
                                'description' => 'Invoice: ' . ($row->invoice->invoice_no ?? $row->invoice->id),
                                'qty_in'    => 0,
                                'qty_out'   => $row->quantity,
                                'rate'      => $row->sale_price ?? 0,
                                'product'   => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Sale Returns
                    $ledger = $ledger->concat(
                        SaleReturnItem::with('saleReturn')
                            ->where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('saleReturn', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date'        => $row->saleReturn->return_date,
                                'type'        => 'Sale Return',
                                'description' => 'Return: ' . ($row->saleReturn->reference_no ?? $row->saleReturn->id),
                                'qty_in'    => $row->qty,
                                'qty_out'   => 0,
                                'rate'      => 0,
                                'product'   => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Production Issue (raw out)
                    $ledger = $ledger->concat(
                        ProductionDetail::with('production')
                            ->where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('production', fn($q) => $q->whereBetween('order_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date'        => $row->production->order_date,
                                'type'        => 'Production Issue',
                                'description' => 'PO-' . str_pad($row->production->id, 4, '0', STR_PAD_LEFT) . ' — Raw Issued',
                                'qty_in'    => 0,
                                'qty_out'   => $row->qty,
                                'rate'      => $row->rate ?? 0,
                                'product'   => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Production Receiving (FG in)
                    $ledger = $ledger->concat(
                        ProductionReceivingDetail::with('receiving')
                            ->where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('receiving', fn($q) => $q->whereBetween('rec_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date'        => $row->receiving->rec_date,
                                'type'        => 'Production Receiving',
                                'description' => 'FG Received — Mfg Cost: ' . number_format($row->manufacturing_cost ?? 0, 2),
                                'qty_in'    => $row->received_qty,
                                'qty_out'   => 0,
                                'rate'      => $row->manufacturing_cost ?? 0,
                                'product'   => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    $itemLedger = $itemLedger->concat($ledger->sortBy('date'));
                }
            }
        }

        // ────────────────────────────────────────────────────────────────────
        // STOCK IN HAND
        // ────────────────────────────────────────────────────────────────────
        if ($tab === 'SR') {
            $selectedItem  = $request->item_id;
            $costingMethod = $request->costing_method ?? 'avg';

            $productsToProcess = $allProducts;

            if ($selectedItem) {
                $parts = explode('-', $selectedItem, 2);
                if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $productsToProcess = $allProducts->where('id', (int)$parts[0])->values();
                    $productsToProcess = $productsToProcess->map(function ($product) use ($parts) {
                        $product           = clone $product;
                        $product->variations = $product->variations->where('id', (int)$parts[1])->values();
                        return $product;
                    });
                } else {
                    $productsToProcess = $allProducts->where('id', (int)$selectedItem)->values();
                }
            }

            foreach ($productsToProcess as $product) {
                $variations = $product->variations->isNotEmpty()
                    ? $product->variations
                    : collect([(object)['id' => null, 'sku' => null, 'stock_quantity' => 0]]);

                foreach ($variations as $var) {
                    // ── Opening stock ─────────────────────────────────────
                    $openingStock = !is_null($var->id)
                        ? (float) ($var->stock_quantity ?? 0)
                        : (float) ($product->opening_stock ?? 0);

                    // ── Stock movements ───────────────────────────────────
                    $purchased = (float) PurchaseInvoiceItem::where('item_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');

                    $purchaseReturn = (float) PurchaseReturnItem::where('item_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');

                    $sold = (float) SaleInvoiceItem::where('product_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');

                    $saleReturn = (float) SaleReturnItem::where('product_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('qty');

                    $issued = (float) ProductionDetail::where('product_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('qty');

                    $received = (float) ProductionReceivingDetail::where('product_id', $product->id)
                        ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                        ->sum('received_qty');

                    $stockQty = $openingStock
                        + $purchased
                        - $purchaseReturn
                        + $saleReturn
                        + $received
                        - $sold
                        - $issued;

                    // ── Cost calculation ──────────────────────────────────
                    $rawCostPerPiece           = 0;
                    $manufacturingCostPerPiece = 0;

                    $isRaw = $product->item_type === 'raw';
                    $isFg  = $product->item_type === 'fg' || is_null($product->item_type);

                    $directlyPurchased = PurchaseInvoiceItem::where('item_id', $product->id)->exists();

                    if ($isRaw || ($isFg && $directlyPurchased)) {
                        // ── Directly purchased (raw or FG bought from vendor) ──────────
                        // Determine which purchase records to use for this variation
                        $varId = $var->id ?? null;

                        if ($varId) {
                            // Check if this specific variation was purchased
                            $hasVarSpecific = PurchaseInvoiceItem::where('item_id', $product->id)
                                ->where('variation_id', $varId)
                                ->exists();

                            if ($hasVarSpecific) {
                                // Use variation-specific purchase records only
                                $purchaseQuery = PurchaseInvoiceItem::where('item_id', $product->id)
                                    ->where('variation_id', $varId);
                            } else {
                                // Check for null-variation purchase records (purchased without specifying variation)
                                $hasNullVariation = PurchaseInvoiceItem::where('item_id', $product->id)
                                    ->whereNull('variation_id')
                                    ->exists();

                                if ($hasNullVariation) {
                                    // Use null-variation records as fallback
                                    $purchaseQuery = PurchaseInvoiceItem::where('item_id', $product->id)
                                        ->whereNull('variation_id');
                                } else {
                                    // This variation was never purchased — cost = 0
                                    $stockInHand->push([
                                        'product'   => $product->name,
                                        'variation' => $var->sku ?? null,
                                        'quantity'  => round($stockQty, 4),
                                        'raw_cost'  => 0,
                                        'mfg_cost'  => 0,
                                        'price'     => 0,
                                        'total'     => 0,
                                    ]);
                                    continue;
                                }
                            }
                        } else {
                            // No variation — query by product only (variation_id irrelevant)
                            $purchaseQuery = PurchaseInvoiceItem::where('item_id', $product->id);
                        }

                        $rawCostPerPiece = match ($costingMethod) {
                            'max'    => (float) ($purchaseQuery->max('price') ?? 0),
                            'min'    => (float) ($purchaseQuery->min('price') ?? 0),
                            'latest' => (float) (optional($purchaseQuery->latest('id')->first())->price ?? 0),
                            default  => (function () use ($purchaseQuery) {
                                $agg = $purchaseQuery
                                    ->selectRaw('SUM(quantity * price) as v, SUM(quantity) as q')
                                    ->first();
                                return ($agg && $agg->q > 0) ? ($agg->v / $agg->q) : 0;
                            })(),
                        };

                        $manufacturingCostPerPiece = 0;

                    } elseif ($isFg && !$directlyPurchased) {
                        // ── Manufactured FG (made in-house from raw materials) ─────────
                        $prodReceivings = ProductionReceivingDetail::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->with('receiving.production.details')
                            ->get();

                        $totalWeightedRawCost = 0;
                        $totalFgQty           = 0;

                        foreach ($prodReceivings as $recDetail) {
                            $production = $recDetail->receiving->production ?? null;
                            if (!$production) continue;

                            $batchFgQty   = (float) $recDetail->received_qty;
                            $batchRawCost = 0;

                            foreach ($production->details as $rawDetail) {
                                $rawRate       = $getPurchaseCost($rawDetail->product_id, $costingMethod, null);
                                $batchRawCost += (float) $rawDetail->qty * $rawRate;
                            }

                            // Total FG from this production batch
                            $batchTotalFg = (float) $production->receivings
                                ->flatMap->details
                                ->where('product_id', $product->id)
                                ->sum('received_qty');

                            if ($batchTotalFg > 0 && $batchFgQty > 0) {
                                $rawCostPerFgUnit      = $batchRawCost / $batchTotalFg;
                                $totalWeightedRawCost += $rawCostPerFgUnit * $batchFgQty;
                            }

                            $totalFgQty += $batchFgQty;
                        }

                        $rawCostPerPiece = $totalFgQty > 0
                            ? $totalWeightedRawCost / $totalFgQty
                            : 0;

                        // Manufacturing cost = weighted avg of mfg cost on receiving details
                        $mfgRows = ProductionReceivingDetail::where('product_id', $product->id)
                            ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                            ->get(['manufacturing_cost', 'received_qty']);

                        $totalMfgValue = $mfgRows->sum(
                            fn($r) => (float) $r->manufacturing_cost * (float) ($r->received_qty ?? 0)
                        );
                        $totalMfgQty = (float) $mfgRows->sum('received_qty');

                        $manufacturingCostPerPiece = $totalMfgQty > 0
                            ? $totalMfgValue / $totalMfgQty
                            : 0;
                    }

                    $costPrice = $rawCostPerPiece + $manufacturingCostPerPiece;

                    $stockInHand->push([
                        'product'   => $product->name,
                        'variation' => $var->sku ?? null,
                        'quantity'  => round($stockQty, 4),
                        'raw_cost'  => round($rawCostPerPiece, 2),
                        'mfg_cost'  => round($manufacturingCostPerPiece, 2),
                        'price'     => round($costPrice, 2),
                        'total'     => round($stockQty * $costPrice, 2),
                    ]);
                }
            }
        }

        // ────────────────────────────────────────────────────────────────────
        // STOCK TRANSFERS
        // ────────────────────────────────────────────────────────────────────
        $transferQuery = StockTransferDetail::with([
            'product',
            'variation',
            'transfer.fromLocation',
            'transfer.toLocation',
        ])->whereHas('transfer', function ($query) use ($request) {
            $query->whereNull('deleted_at');

            if ($request->from_location_id) {
                $query->where('from_location_id', $request->from_location_id);
            }
            if ($request->to_location_id) {
                $query->where('to_location_id', $request->to_location_id);
            }
            if ($request->from_date && $request->to_date) {
                $query->whereBetween('date', [$request->from_date, $request->to_date]);
            }
        });

        $stockTransfers = $transferQuery->get()->map(fn($row) => [
            'date'      => $row->transfer->date ?? null,
            'reference' => $row->transfer->id ?? null,
            'product'   => $row->product->name ?? null,
            'variation' => $row->variation->sku ?? null,
            'from'      => $row->transfer->fromLocation->name ?? '',
            'to'        => $row->transfer->toLocation->name ?? '',
            'quantity'  => $row->quantity,
        ]);

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