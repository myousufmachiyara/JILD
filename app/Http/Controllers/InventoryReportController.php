<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnItem;
use App\Models\ProductionDetail;
use App\Models\ProductionReceivingDetail;
use App\Models\StockTransferDetail;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $tab = $request->tab ?? 'IL'; // Default tab: Item Ledger
        $productId = $request->item_id ?? null;
        $variationId = $request->variation_id ?? null;
        $from = $request->from_date ?? now()->startOfMonth()->toDateString();
        $to   = $request->to_date ?? now()->toDateString();
        $locationId = $request->location_id ?? null;

        // Fetch products with variations
        $products = Product::with(['variations' => function($q) use ($variationId) {
            if ($variationId) {
                $q->where('id', $variationId);
            }
        }])->when($productId, function($q) use ($productId) {
            $q->where('id', $productId);
        })->get();

        // --------------------------
        // ITEM LEDGER
        // --------------------------
        $itemLedger = collect();
        if ($tab === 'IL' && $productId) {
            foreach ($products as $product) {
                $variations = $product->variations->isNotEmpty() ? $product->variations : collect([$product]);

                foreach ($variations as $var) {
                    $ledger = collect();

                    // Purchases
                    $ledger = $ledger->concat(
                        PurchaseInvoiceItem::where('product_id', $product->id)
                            ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('invoice', fn($q) => $q->whereBetween('invoice_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->invoice->invoice_date,
                                'type' => 'Purchase',
                                'description' => 'Bill No: ' . $row->invoice->bill_no,
                                'qty_in' => $row->quantity,
                                'qty_out' => 0,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Purchase Returns
                    $ledger = $ledger->concat(
                        PurchaseReturnItem::where('product_id', $product->id)
                            ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('return', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->return->return_date,
                                'type' => 'Purchase Return',
                                'description' => 'Return No: ' . $row->return->reference_no,
                                'qty_in' => 0,
                                'qty_out' => $row->quantity,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Sales
                    $ledger = $ledger->concat(
                        SaleInvoiceItem::where('product_id', $product->id)
                            ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('invoice', fn($q) => $q->whereBetween('date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->invoice->date,
                                'type' => 'Sale',
                                'description' => 'Invoice No: ' . $row->invoice->invoice_no,
                                'qty_in' => 0,
                                'qty_out' => $row->quantity,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Sale Returns
                    $ledger = $ledger->concat(
                        SaleReturnItem::where('product_id', $product->id)
                            ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('saleReturn', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->saleReturn->return_date,
                                'type' => 'Sale Return',
                                'description' => 'Return No: ' . $row->saleReturn->reference_no,
                                'qty_in' => $row->quantity,
                                'qty_out' => 0,
                                'product' => $product->name,
                                'variation' => $var->sku ?? null,
                            ])
                    );

                    // Production Issue (Out)
                    $ledger = $ledger->concat(
                        ProductionDetail::where('product_id', $product->id)
                            ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
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

                    // Production Receiving (In)
                    $ledger = $ledger->concat(
                        ProductionReceivingDetail::where('product_id', $product->id)
                            ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                            ->whereHas('receiving', fn($q) => $q->whereBetween('rec_date', [$from, $to]))
                            ->get()
                            ->map(fn($row) => [
                                'date' => $row->receiving->rec_date,
                                'type' => 'Production Receiving',
                                'description' => 'Manufactured Goods Received',
                                'qty_in' => $row->qty,
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
        // STOCK IN HAND
        // --------------------------
        $stockInHand = collect();
        if ($tab === 'SR') {
            foreach ($products as $product) {
                $variations = $product->variations->isNotEmpty() ? $product->variations : collect([$product]);

                foreach ($variations as $var) {
                    $qtyIn = 0;
                    $qtyOut = 0;

                    $qtyIn += PurchaseInvoiceItem::where('item_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');
                    $qtyIn += SaleReturnItem::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('qty');
                    $qtyIn += ProductionReceivingDetail::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('received_qty');

                    $qtyOut += SaleInvoiceItem::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');
                    $qtyOut += PurchaseReturnItem::where('item_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');
                    $qtyOut += ProductionDetail::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('qty');

                    $stockInHand->push([
                        'product' => $product->name,
                        'variation' => $var->sku ?? null,
                        'quantity' => $qtyIn - $qtyOut,
                    ]);
                }
            }
        }

        // --------------------------
        // STOCK TRANSFER
        // --------------------------
        $stockTransfer = collect();
        if ($tab === 'STR') {
            $transfers = StockTransferDetail::with(['transfer', 'product', 'variation', 'transfer.from_location', 'transfer.to_location'])
                ->whereHas('transfer', fn($q) => $q->whereBetween('date', [$from, $to]))
                ->when($locationId, function($q) use ($locationId) {
                    $q->where(function($inner) use ($locationId) {
                        $inner->where('from_location_id', $locationId)
                              ->orWhere('to_location_id', $locationId);
                    });
                })
                ->get();

            foreach ($transfers as $t) {
                $stockTransfer->push([
                    'date' => $t->transfer->date,
                    'from_location' => $t->transfer->from_location->name ?? '',
                    'to_location' => $t->transfer->to_location->name ?? '',
                    'product' => $t->product->name,
                    'variation' => $t->variation->sku ?? null,
                    'quantity' => $t->quantity,
                ]);
            }
        }

        // --------------------------
        // NON-MOVING ITEMS
        // --------------------------
        $nonMovingItems = collect();
        if ($tab === 'NMI') {
            $thresholdDate = now()->subMonths(3)->toDateString(); // Last 3 months

            foreach ($products as $product) {
                $variations = $product->variations->isNotEmpty() ? $product->variations : collect([$product]);

                foreach ($variations as $var) {
                    $lastTx = collect()
                        ->concat(PurchaseInvoiceItem::where('product_id', $product->id)->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))->pluck('created_at'))
                        ->concat(PurchaseReturnItem::where('product_id', $product->id)->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))->pluck('created_at'))
                        ->concat(SaleInvoiceItem::where('product_id', $product->id)->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))->pluck('created_at'))
                        ->concat(SaleReturnItem::where('product_id', $product->id)->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))->pluck('created_at'))
                        ->concat(ProductionDetail::where('product_id', $product->id)->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))->pluck('created_at'))
                        ->concat(ProductionReceivingDetail::where('product_id', $product->id)->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))->pluck('created_at'))
                        ->sortDesc()
                        ->first();

                    $daysInactive = $lastTx ? now()->diffInDays($lastTx) : null;

                    if (!$lastTx || $lastTx < $thresholdDate) {
                        $nonMovingItems->push([
                            'product' => $product->name,
                            'variation' => $var->sku ?? null,
                            'last_date' => $lastTx,
                            'days_inactive' => $daysInactive,
                        ]);
                    }
                }
            }
        }

        // --------------------------
        // REORDER LEVEL
        // --------------------------
        $reorderLevel = collect();
        if ($tab === 'ROL') {
            foreach ($products as $product) {
                $variations = $product->variations->isNotEmpty() ? $product->variations : collect([$product]);

                foreach ($variations as $var) {
                    $qtyIn = 0;
                    $qtyOut = 0;

                    $qtyIn += PurchaseInvoiceItem::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');
                    $qtyIn += SaleReturnItem::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');
                    $qtyIn += ProductionReceivingDetail::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('qty');

                    $qtyOut += SaleInvoiceItem::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');
                    $qtyOut += PurchaseReturnItem::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('quantity');
                    $qtyOut += ProductionDetail::where('product_id', $product->id)
                        ->when($var->id ?? null, fn($q) => $q->where('variation_id', $var->id))
                        ->sum('qty');

                    $stockInHandQty = $qtyIn - $qtyOut;

                    // use product/variation reorder_level column if exists, else fallback to 50
                    $reorderLevelValue = $var->reorder_level ?? $product->reorder_level ?? 50;

                    if ($stockInHandQty <= $reorderLevelValue) {
                        $reorderLevel->push([
                            'product' => $product->name,
                            'variation' => $var->sku ?? null,
                            'stock_inhand' => $stockInHandQty,
                            'reorder_level' => $reorderLevelValue,
                        ]);
                    }
                }
            }
        }

        return view('reports.inventory_reports', compact(
            'products',
            'tab',
            'itemLedger',
            'stockInHand',
            'stockTransfer',
            'nonMovingItems',
            'reorderLevel',
            'from',
            'to',
            'locationId'
        ));
    }
}
