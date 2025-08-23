<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Production;
use App\Models\ProductionDetail;
use App\Models\ProductionReceiving;
use App\Models\ProductionReceivingDetail;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\PaymentVoucher;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function itemLedger(Request $request)
    {
        $items = Product::all();
        $itemId = $request->input('item_id');
        $from = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? Carbon::now()->toDateString();

        $ledger = collect();

        if ($itemId && $from && $to) {

            // ✅ Purchases
            $purchases = PurchaseInvoiceItem::where('item_id', $itemId)
                ->whereHas('invoice', fn($q) => $q->whereBetween('invoice_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->invoice->invoice_date,
                    'type' => 'Purchase',
                    'description' => 'Bill No: '.$row->invoice->bill_no,
                    'qty_in' => $row->quantity,
                    'qty_out' => 0,
                ]);

            // ✅ Purchase Returns
            $purchaseReturns = PurchaseReturnItem::where('item_id', $itemId)
                ->whereHas('return', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->return->return_date,
                    'type' => 'Purchase Return',
                    'description' => 'Return No: '.$row->return->reference_no,
                    'qty_in' => 0,
                    'qty_out' => $row->quantity,
                ]);

            // ✅ Sales
            $sales = SaleInvoiceItem::where('product_id', $itemId)
                ->whereHas('invoice', fn($q) => $q->whereBetween('date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->invoice->date,
                    'type' => 'Sale',
                    'description' => 'Invoice No: '.$row->invoice->invoice_no,
                    'qty_in' => 0,
                    'qty_out' => $row->quantity,
                ]);

            // ✅ Sale Returns
            $saleReturns = SaleReturnItem::where('product_id', $itemId)
                ->whereHas('saleReturn', fn($q) => $q->whereBetween('return_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->saleReturn->return_date,
                    'type' => 'Sale Return',
                    'description' => 'Return No: '.$row->saleReturn->reference_no,
                    'qty_in' => $row->quantity,
                    'qty_out' => 0,
                ]);

            // ✅ Production Raw Issue (goes OUT)
            $productions = ProductionDetail::where('product_id', $itemId)
                ->whereHas('production', fn($q) => $q->whereBetween('order_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->production->order_date,
                    'type' => 'Production Raw Issue',
                    'description' => 'Raw Material Issued',
                    'qty_in' => 0,
                    'qty_out' => $row->qty,
                ]);

            // ✅ Production Receiving (goes IN)
            $productionReceivings = ProductionReceivingDetail::where('product_id', $itemId)
                ->whereHas('receiving', fn($q) => $q->whereBetween('rec_date', [$from, $to]))
                ->get()
                ->map(fn($row) => [
                    'date' => $row->receiving->rec_date,
                    'type' => 'Production Receiving',
                    'description' => 'Manufactured Goods Received',
                    'qty_in' => $row->qty,
                    'qty_out' => 0,
                ]);

            // ✅ Merge all
            $ledger = $purchases
                ->concat($purchaseReturns)
                ->concat($sales)
                ->concat($saleReturns)
                ->concat($productions)
                ->concat($productionReceivings)
                ->sortBy('date')
                ->values();
        }

        return view('reports.item_ledger', compact('items', 'ledger', 'itemId', 'from', 'to'));
    }

    public function partyLedger(Request $request)
    {
        $partyId = $request->vendor_id;  // ✅ from query string
        $from = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? Carbon::now()->toDateString();
        $transactions = collect();
        
        // Purchase Invoices with Items (Invoice-wise total calculation)
        $purchases = PurchaseInvoice::with('items')
            ->where('vendor_id', $partyId)
            ->whereBetween('invoice_date', [$from, $to])
            ->get()
            ->map(function($invoice) {
                // Calculate total amount = sum of all item qty * rate
                $total = $invoice->items->sum(function($item) {
                    return $item->quantity * $item->price;
                });

                return [
                    'date' => $invoice->invoice_date,
                    'type' => 'Purchase',
                    'description' => 'Purchase Invoice #' . $invoice->id,
                    'debit' => $total,
                    'credit' => 0,
                ];
            });

        $transactions = $transactions->merge($purchases);

        // Purchase Returns (Return reduces vendor payable)
        $purchaseReturns = PurchaseReturn::with('items')
            ->where('vendor_id', $partyId)
            ->whereBetween('return_date', [$from, $to])
            ->get()
            ->map(function($return) {
                // Calculate total = sum of returned qty * rate
                $total = $return->items->sum(function($item) {
                    return $item->quantity * $item->price;
                });

                return [
                    'date' => $return->return_date,
                    'type' => 'Purchase Return',
                    'description' => 'Purchase Return #' . $return->id,
                    'debit' => 0,
                    'credit' => $total,
                ];
            });

        $transactions = $transactions->merge($purchaseReturns);

        // Production Receivings with Details (Receiving increases vendor payable)
        $receivings = ProductionReceiving::with(['details', 'production'])
        ->whereHas('production', function ($q) use ($partyId) {
            $q->where('vendor_id', $partyId);
        })
        ->whereBetween('rec_date', [$from, $to])
        ->get()
        ->map(function($receiving) {
            // ✅ Calculate total from details
            $total = $receiving->details->sum(function($item) {
                return $item->received_qty * $item->manufacturing_cost;
            });

            return [
                'date' => $receiving->rec_date,
                'type' => 'Production Receiving',
                'description' => 'Production Receiving #' . $receiving->id,
                'debit' => $total,
                'credit' => 0,
            ];
        });

        $transactions = $transactions->merge($receivings);

        // Sale Invoices (customer receivable)
        $sales = SaleInvoice::with('items')
            ->where('account_id', $partyId)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->map(function($invoice) {
                // Invoice-wise total = sum of item qty * price - discount%
                $total = $invoice->items->sum(function($item) {
                    
                    $lineTotal = $item->quantity * $item->sale_price;
                    $discountAmount = ($lineTotal * $item->discount) / 100; // percentage discount
                    return $lineTotal - $discountAmount;
                });

                return [
                    'date' => $invoice->date,
                    'type' => 'Sale',
                    'description' => 'Sale Invoice #' . $invoice->id,
                    'debit' => 0,
                    'credit' => $total,   // Customer owes us (credit in our books)
                ];
            });

        $transactions = $transactions->merge($sales);


        // Sale Returns (reduces receivable)
        $saleReturns = SaleReturn::with('items')
            ->where('account_id', $partyId)
            ->whereBetween('return_date', [$from, $to])
            ->get()
            ->map(function($return) {
                $total = $return->items->sum(function($item) {
                    return $item->qty * $item->price;
                });

                return [
                    'date' => $return->return_date,
                    'type' => 'Sale Return',
                    'description' => 'Sale Return #' . $return->id,
                    'debit' => $total,   // We give back (debit to reduce receivable)
                    'credit' => 0,
                ];
            });

        $transactions = $transactions->merge($saleReturns);

        // 9️⃣ Payment Voucher
        $payments = PaymentVoucher::whereBetween('date', [$from, $to])
            ->where(function($q) use($partyId) {
                $q->where('ac_dr_sid', $partyId)->orWhere('ac_cr_sid', $partyId);
            })
            ->get()
            ->map(function($pv) use ($partyId) {
                return [
                    'date' => $pv->date,
                    'type' => 'Payment Voucher',
                    'description' => 'Payment Voucher #' . $pv->id,
                    'debit' => $pv->ac_dr_sid == $partyId ? $pv->amount : 0,
                    'credit' => $pv->ac_cr_sid == $partyId ? $pv->amount : 0,
                ];
            });
        $transactions = $transactions->merge($payments);

        // Sort by date
        $transactions = $transactions->sortBy('date')->values();
    
        return view('reports.party_ledger', [
            'ledger' => $transactions,
            'from'   => $from,
            'to'     => $to,
            'vendorId' => $partyId,
            'account' => ChartOfAccounts::all(), // so you can show dropdown
        ]);
    }   

    public function purchase() {
        return view('reports.purchase');
    }

    public function purchaseReturn() {
        return view('reports.purchase_return');
    }

    public function production() {
        return view('reports.production');
    }

    public function productionReceiving() {
        return view('reports.production_receiving');
    }

    public function sales() {
        return view('reports.sales');
    }

    public function saleReturn() {
        return view('reports.sale_return');
    }

    public function payments() {
        return view('reports.payments');
    }

}
