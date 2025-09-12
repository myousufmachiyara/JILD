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
        $locations = Location::all();

        // parse product and variation if provided in "productId-variationId" format
        $productId = null;
        $variationId = null;
        if ($selected) {
            if (str_contains($selected, '-')) {
                [$p, $v] = explode('-', $selected);
                $productId = (int) $p;
                $variationId = $v !== '' ? (int) $v : null;
            } else {
                $productId = (int) $selected;
            }
        }

        // load all products (for dropdown)
        $allProducts = Product::with('variations')->get();

        // --------------------------
        // ITEM LEDGER
        // --------------------------
        $itemLedger = collect();
        if ($tab === 'IL' && $productId) {
            $product = $allProducts->firstWhere('id', $productId);
            if ($product) {
                // determine variations to iterate: either the selected variation (if passed),
                // the product's variations, or a single dummy variation with id = null
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

                    // Purchases (purchase_invoice_items uses item_id)
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

                    // Purchase Returns (purchase_return_items.item_id)
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

                    // Sales (sale_invoice_items.product_id)
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

                    // Sale Returns (sale_return_items.product_id, column name for qty is `qty`)
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

                    // Production Issue (production_details.product_id, qty)
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

                    // Production Receiving (production_receiving_details.product_id, received_qty)
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

        // --------------------------
        // STOCK INHAND (all products)
        // --------------------------
        $stockInHand = collect();
        foreach ($allProducts as $product) {
            $variations = $product->variations->isNotEmpty()
                ? $product->variations
                : collect([(object)['id' => null, 'sku' => null]]);

            foreach ($variations as $var) {
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

                $issued = ProductionDetail::where('product_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->sum('qty');

                $received = ProductionReceivingDetail::where('product_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->sum('received_qty');

                $stockQty = ($purchased - $purchaseReturn + $saleReturn + $received) - ($sold + $issued);

                // get last purchase price fallback to product selling_price
                $lastPurchase = PurchaseInvoiceItem::where('item_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->latest('id')
                    ->first();

                $price = $lastPurchase->price ?? $product->selling_price ?? 0;

                $stockInHand->push([
                    'product'   => $product->name,
                    'variation' => $var->sku ?? null,
                    'quantity'  => $stockQty,
                    'price'     => $price,
                    'total'     => $stockQty * $price,
                ]);
            }
        }

        // STOCK TRANSFERS
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


        // --------------------------
        // NON-MOVING ITEMS (last 3 months threshold)
        // --------------------------
        $nonMovingItems = collect();
        $thresholdDate = Carbon::now()->subMonths(3);

        foreach ($allProducts as $product) {
            $variations = $product->variations->isNotEmpty()
                ? $product->variations
                : collect([(object)['id' => null, 'sku' => null]]);

            foreach ($variations as $var) {
                $dates = collect();

                $d = PurchaseInvoiceItem::where('item_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->latest('created_at')->value('created_at');
                if ($d) $dates->push($d);

                $d = PurchaseReturnItem::where('item_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->latest('created_at')->value('created_at');
                if ($d) $dates->push($d);

                $d = SaleInvoiceItem::where('product_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->latest('created_at')->value('created_at');
                if ($d) $dates->push($d);

                $d = SaleReturnItem::where('product_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->latest('created_at')->value('created_at');
                if ($d) $dates->push($d);

                $d = ProductionDetail::where('product_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->latest('created_at')->value('created_at');
                if ($d) $dates->push($d);

                $d = ProductionReceivingDetail::where('product_id', $product->id)
                    ->when(!is_null($var->id), fn($q) => $q->where('variation_id', $var->id))
                    ->latest('created_at')->value('created_at');
                if ($d) $dates->push($d);

                $lastTx = $dates->max();

                if (!$lastTx || Carbon::parse($lastTx)->lessThan($thresholdDate)) {
                    $daysInactive = $lastTx ? Carbon::now()->diffInDays(Carbon::parse($lastTx)) : null;
                    $nonMovingItems->push([
                        'product' => $product->name,
                        'variation' => $var->sku ?? null,
                        'last_date' => $lastTx ? Carbon::parse($lastTx)->toDateString() : null,
                        'days_inactive' => $daysInactive,
                    ]);
                }
            }
        }

        // --------------------------
        // REORDER LEVEL (compare stockInHand with product.reorder_level)
        // --------------------------
        $reorderLevel = collect();
        foreach ($stockInHand as $stock) {
            $productObj = $allProducts->firstWhere('name', $stock['product']);
            $reorderValue = $productObj->reorder_level ?? 50;
            if ($stock['quantity'] <= $reorderValue) {
                $reorderLevel->push([
                    'product' => $stock['product'],
                    'variation' => $stock['variation'],
                    'stock_inhand' => $stock['quantity'],
                    'reorder_level' => $reorderValue,
                ]);
            }
        }

        return view('reports.inventory_reports', [
            'products'      => $allProducts,
            'tab'           => $tab,
            'itemLedger'    => $itemLedger->sortBy('date')->values(),
            'stockInHand'   => $stockInHand,
            'stockTransfers' => $stockTransfers,                // <-- matches Blade variable
            'nonMovingItems'=> $nonMovingItems,
            'reorderLevel'  => $reorderLevel,
            'from'          => $from,
            'to'            => $to,
            'locationId'    => $locationId,
            'locations'      => $locations,   // <-- added

        ]);
    }
}
