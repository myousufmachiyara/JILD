<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\StockTransaction;
use App\Models\StockTransfer;

class InventoryReportController extends Controller
{
    public function inventoryReports(Request $request)
    {
        $items = Product::all();
        $tab = $request->tab ?? 'IL'; // default tab = Item Ledger

        // -------------------------------
        // ITEM LEDGER
        // -------------------------------
        $from = $request->from_date ?? now()->startOfMonth()->format('Y-m-d');
        $to   = $request->to_date ?? now()->format('Y-m-d');
        $itemLedger = collect();
        if ($tab === 'IL' && $request->item_id) {
            $itemLedger = StockTransaction::where('item_id', $request->item_id)
                ->whereBetween('date', [$from, $to])
                ->get();
        }

        // -------------------------------
        // STOCK INHAND
        // -------------------------------
        $stockInhand = collect();
        if ($tab === 'SR') {
            $query = Product::query();
            if ($request->item_id) $query->where('id', $request->item_id);
            $stockInhand = $query->withSum('stockTransactions as qty_in_total', 'qty_in')
                                  ->withSum('stockTransactions as qty_out_total', 'qty_out')
                                  ->get()
                                  ->map(function($item) {
                                      $item->quantity = $item->qty_in_total - $item->qty_out_total;
                                      return $item;
                                  });
        }

        // STOCK TRANSFER
        $stockTransfer = collect();
        if ($tab === 'STR') {
            $from = $request->from_date ?? now()->startOfMonth()->format('Y-m-d');
            $to   = $request->to_date ?? now()->format('Y-m-d');
            $stockTransfer = StockTransfer::whereBetween('date', [$from, $to])->get();
        }

        // NON-MOVING ITEMS
        $nonMovingItems = collect();
        if ($tab === 'NMI') {
            $from = $request->from_date ?? now()->subMonths(3)->format('Y-m-d'); // e.g., last 3 months
            $to   = $request->to_date ?? now()->format('Y-m-d');

            $nonMovingItems = Item::with(['stockTransactions' => function($q) use($from, $to) {
                $q->whereBetween('date', [$from, $to]);
            }])->get()->map(function($item){
                $lastTx = $item->stockTransactions->sortByDesc('date')->first();
                $item->last_date = $lastTx?->date;
                $item->days_inactive = $lastTx ? now()->diffInDays($lastTx->date) : null;
                return $item;
            });
        }


        // -------------------------------
        // REORDER LEVEL
        // -------------------------------
        $reorderLevel = collect();
        if ($tab === 'ROL') {
            $reorderLevel = Product::withSum('stockTransactions as qty_in_total', 'qty_in')
                                ->withSum('stockTransactions as qty_out_total', 'qty_out')
                                ->get()
                                ->map(function($item){
                                    $item->stock_inhand = $item->qty_in_total - $item->qty_out_total;
                                    return $item;
                                });
        }

        return view('reports.inventory_reports', compact(
            'items', 'tab', 'itemLedger', 'stockInhand', 'stockTransfer', 'nonMovingItems', 'reorderLevel', 'from', 'to'
        ));
    }
}
