<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\ChartOfAccounts;
use Carbon\Carbon;

class PurchaseReportController extends Controller
{
    public function purchaseReports(Request $request)
    {
        $tab = $request->get('tab', 'PUR'); // default: Purchase Register

        // Default date range (last 30 days)
        $from = $request->get('from_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to   = $request->get('to_date', Carbon::now()->format('Y-m-d'));

        // ✅ Vendors actually come from  ChartOfAccounts
        $vendors =  ChartOfAccounts::where('account_type', 'vendor')->get();

        $purchaseRegister = collect();
        $purchaseReturns = collect();
        $vendorWisePurchase = collect();

        // --- PURCHASE REGISTER ---
        if ($tab == 'PUR') {
            $query = PurchaseInvoice::with('vendor', 'items')
                ->whereBetween('invoice_date', [$from, $to]);

            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->vendor_id);
            }

            $purchaseRegister = $query->get()->flatMap(function ($purchase) {
                return $purchase->items->map(function ($item) use ($purchase) {
                    return (object)[
                        'date'        => $purchase->invoice_date,
                        'invoice_no'  => $purchase->bill_no ?? $purchase->id,
                        'vendor_name' => $purchase->vendor->name ?? '',
                        'item_name'   => $item->item_name,
                        'quantity'    => $item->quantity,
                        'rate'        => $item->price,
                        'total'       => $item->quantity * $item->price,
                    ];
                });
            });
        }

        // --- PURCHASE RETURNS ---
        if ($tab == 'PR') {
            $query = PurchaseReturn::with('vendor', 'items')
                ->whereBetween('date', [$from, $to]);

            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->vendor_id);
            }

            $purchaseReturns = $query->get()->flatMap(function ($return) {
                return $return->items->map(function ($item) use ($return) {
                    return (object)[
                        'date'        => $return->date,
                        'return_no'   => $return->return_no ?? $return->id,
                        'vendor_name' => $return->vendor->name ?? '',
                        'item_name'   => $item->item_name,
                        'quantity'    => $item->quantity,
                        'rate'        => $item->price,
                        'total'       => $item->quantity * $item->price,
                    ];
                });
            });
        }

        // --- VENDOR-WISE PURCHASES ---
        if ($tab == 'VWP') {
            $query = PurchaseInvoice::with('vendor', 'items')
                ->whereBetween('date', [$from, $to]);

            if ($request->filled('vendor_id')) {
                $query->where('vendor_id', $request->vendor_id);
            }

            $vendorWisePurchase = $query->get()->groupBy('vendor_id')->map(function ($purchases, $vendorId) {
                $vendor = $purchases->first()->vendor->name ?? 'Unknown Vendor';

                $totalQty = 0;
                $totalAmount = 0;

                foreach ($purchases as $purchase) {
                    foreach ($purchase->items as $item) {
                        $totalQty += $item->quantity;
                        $totalAmount += $item->quantity * $item->price;
                    }
                }

                return (object)[
                    'vendor_name'  => $vendor,
                    'total_qty'    => $totalQty,
                    'total_amount' => $totalAmount,
                ];
            })->values();
        }

        return view('reports.purchase_reports', compact(
            'tab',
            'from',
            'to',
            'vendors',
            'purchaseRegister',
            'purchaseReturns',
            'vendorWisePurchase'
        ));
    }
}
