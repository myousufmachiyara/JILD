<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Variation;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturnItem;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturnItem;
use App\Models\ProductionDetail;
use App\Models\ProductionReceivingDetail;
use App\Models\StockTransfer;
use App\Models\StockTransferDetail;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $tab = $request->tab ?? 'IL';
        $productId = $request->product_id ?? null;
        $variationId = $request->variation_id ?? null;
        $locationId = $request->location_id ?? null;

        $products = Product::with('variations')->get();
        $from = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? Carbon::now()->toDateString();

        // -------------------- ITEM LEDGER --------------------
        $itemLedger = collect();
        if ($tab === 'IL' && $productId) {
            $filterVariation = $variationId ? fn($q) => $q->where('variation_id', $variationId) : fn($q) => $q;

            $purchases = PurchaseInvoiceItem::where('product_id', $productId)
                ->where($filterVariation)
                ->whereHas('invoice', fn($q) => $q->whereBetween('invoice_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->invoice->invoice_date,
                    'type' => 'Purchase',
                    'description' => 'Bill No: '.$row->invoice->bill_no,
                    'qty_in' => $row->quantity,
                    'qty_out' => 0,
                ]);

            $purchaseReturns = PurchaseReturnItem::where('product_id', $productId)
                ->where($filterVariation)
                ->whereHas('return', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->return->return_date,
                    'type' => 'Purchase Return',
                    'description' => 'Return No: '.$row->return->reference_no,
                    'qty_in' => 0,
                    'qty_out' => $row->quantity,
                ]);

            $sales = SaleInvoiceItem::where('product_id', $productId)
                ->where($filterVariation)
                ->whereHas('invoice', fn($q) => $q->whereBetween('date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->invoice->date,
                    'type' => 'Sale',
                    'description' => 'Invoice No: '.$row->invoice->invoice_no,
                    'qty_in' => 0,
                    'qty_out' => $row->quantity,
                ]);

            $saleReturns = SaleReturnItem::where('product_id', $productId)
                ->where($filterVariation)
                ->whereHas('saleReturn', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->saleReturn->return_date,
                    'type' => 'Sale Return',
                    'description' => 'Return No: '.$row->saleReturn->reference_no,
                    'qty_in' => $row->quantity,
                    'qty_out' => 0,
                ]);

            $productions = ProductionDetail::where('product_id', $productId)
                ->where($filterVariation)
                ->whereHas('production', fn($q) => $q->whereBetween('order_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->production->order_date,
                    'type' => 'Production Raw Issue',
                    'description' => 'Raw Material Issued',
                    'qty_in' => 0,
                    'qty_out' => $row->qty,
                ]);

            $productionReceivings = ProductionReceivingDetail::where('product_id', $productId)
                ->where($filterVariation)
                ->whereHas('receiving', fn($q) => $q->whereBetween('rec_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->receiving->rec_date,
                    'type' => 'Production Receiving',
                    'description' => 'Manufactured Goods Received',
                    'qty_in' => $row->qty,
                    'qty_out' => 0,
                ]);

            $itemLedger = $purchases
                ->concat($purchaseReturns)
                ->concat($sales)
                ->concat($saleReturns)
                ->concat($productions)
                ->concat($productionReceivings)
                ->sortBy('date')
                ->values();
        }

        // -------------------- STOCK IN HAND --------------------
        $stockInHand = collect();
        if ($tab === 'SR') {
            $query = Product::query();
            if ($productId) $query->where('id', $productId);
            $stockInHand = $query->with(['variations' => function($q) use ($variationId) {
                    if ($variationId) $q->where('id', $variationId);
                }])
                ->withSum('stockTransactions as qty_in_total', 'qty_in')
                ->withSum('stockTransactions as qty_out_total', 'qty_out')
                ->get()
                ->map(function($item) {
                    $item->quantity = $item->qty_in_total - $item->qty_out_total;
                    return $item;
                });
        }

        // -------------------- STOCK TRANSFER --------------------
        $stockTransfer = collect();
        if ($tab === 'STR') {
            $query = StockTransferDetail::query()
                ->with('transfer');

            if ($productId) $query->where('product_id', $productId);
            if ($variationId) $query->where('variation_id', $variationId);
            if ($locationId) $query->where('location_id', $locationId);

            $stockTransfer = $query->whereHas('transfer', fn($q) => $q->whereBetween('date', [$from, $to]))
                                    ->get();
        }

        // -------------------- NON MOVING ITEMS --------------------
        $nonMovingItems = collect();
        if ($tab === 'NMI') {
            $months = 3;
            $nonMovingItems = Product::with(['variations' => function($q) use ($variationId) {
                if ($variationId) $q->where('id', $variationId);
            }])->get()->map(function($item) use($from, $to, $months){
                $lastTx = $item->stockTransactions
                                ->whereBetween('date', [Carbon::now()->subMonths($months), Carbon::now()])
                                ->sortByDesc('date')
                                ->first();
                $item->last_date = $lastTx?->date;
                $item->days_inactive = $lastTx ? Carbon::now()->diffInDays($lastTx->date) : null;
                return $item;
            });
        }

        // -------------------- REORDER LEVEL --------------------
        $reorderLevel = collect();
        if ($tab === 'ROL') {
            $reorderLevel = Product::with(['variations' => function($q) use ($variationId) {
                if ($variationId) $q->where('id', $variationId);
            }])
            ->withSum('stockTransactions as qty_in_total', 'qty_in')
            ->withSum('stockTransactions as qty_out_total', 'qty_out')
            ->get()
            ->map(function($item){
                $item->stock_inhand = $item->qty_in_total - $item->qty_out_total;
                return $item;
            })->filter(fn($i) => $i->stock_inhand < 50); // less than 50
        }

        return view('reports.inventory_reports', compact(
            'products', 'tab', 'itemLedger', 'stockInHand', 'stockTransfer',
            'nonMovingItems', 'reorderLevel', 'from', 'to', 'productId', 'variationId', 'locationId'
        ));
    }
}
