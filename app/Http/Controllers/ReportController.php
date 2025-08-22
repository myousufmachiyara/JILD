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

class ReportController extends Controller
{
    public function itemLedger(Request $request)
    {
        $items = Product::all();
        $itemId = $request->input('item_id');
        $from   = $request->input('from_date');
        $to     = $request->input('to_date');

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
        $to   = $request->to_date   ?? Carbon::now()->endOfMonth()->toDateString();
        $transactions = collect();
        
        // 1️⃣ Purchase (main entry)
        $purchases = PurchaseInvoice::with('vendor')
            ->where('vendor_id', $partyId)
            ->whereBetween('invoice_date', [$from, $to])
            ->get()
            ->map(function($purchase) {
                return [
                    'date' => $purchase->date,
                    'type' => 'Purchase',
                    'description' => 'Purchase #' . $purchase->id,
                    'debit' => $purchase->total_amount,
                    'credit' => 0,
                ];
            });
        $transactions = $transactions->merge($purchases);

        // 2️⃣ Purchase Item Details
        $purchaseItems = PurchaseInvoiceItem::with('invoice')
            ->whereHas('invoice', function($q) use($partyId, $from, $to) {
                $q->where('vendor_id', $partyId)->whereBetween('invoice_date', [$from, $to]);
            })
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->invoice->invoice_date,
                    'type' => 'Purchase Item',
                    'description' => 'Purchase Item of PO #' . $item->invoice->id,
                    'debit' => $item->rate * $item->qty,
                    'credit' => 0,
                ];
            });
        $transactions = $transactions->merge($purchaseItems);

        // 3️⃣ Production (main entry)
        $productions = Production::with('vendor')
            ->where('production_type', 'sale_leather')
            ->where('vendor_id', $partyId)
            ->whereBetween('order_date', [$from, $to])
            ->get()
            ->map(function($production) {
                return [
                    'date' => $production->order_date,
                    'type' => 'Production',
                    'description' => 'Production #' . $production->id,
                    'debit' => $production->total_amount,
                    'credit' => 0,
                ];
            });
        $transactions = $transactions->merge($productions);

        // 4️⃣ Production Details (only sale_leather)
        $productionDetails = ProductionDetail::with('production')
            ->whereHas('production', function($q) use($partyId, $from, $to) {
                $q->where('vendor_id', $partyId)
                ->whereBetween('order_date', [$from, $to]);
            })
            ->get()
            ->map(function($detail) {
                return [
                    'date' => $detail->production->order_date,
                    'type' => 'Production Raw',
                    'description' => 'Production Raw Material Usage',
                    'debit' => $detail->qty * $detail->rate,
                    'credit' => 0,
                ];
            });
        $transactions = $transactions->merge($productionDetails);

       // 5️⃣ Production Receiving (main)
        $receivings = ProductionReceiving::with('production')
            ->whereHas('production', function($q) use ($partyId) {
                $q->where('vendor_id', $partyId);
            })
            ->whereBetween('rec_date', [$from, $to])
            ->get()
            ->map(function($rec) {
                return [
                    'date'        => $rec->rec_date,
                    'type'        => 'Production Receiving',
                    'description' => 'Production Receiving #' . $rec->id,
                    'debit'       => 0,
                    'credit'      => $rec->total_amount, // manufacturing cost to pay
                ];
            });

        $transactions = $transactions->merge($receivings);

        // 6️⃣ Production Receiving Details
        $receivingDetails = ProductionReceivingDetail::with(['receiving.production'])
            ->whereHas('receiving.production', function($q) use($partyId, $from, $to) {
                $q->where('vendor_id', $partyId)
                ->whereBetween('rec_date', [$from, $to]);
            })
            ->get()
            ->map(function($detail) {
                return [
                    'date' => $detail->receiving->date,
                    'type' => 'Production Cost',
                    'description' => 'Manufacturing Cost Payment (Production #' . $detail->receiving->production->id . ')',
                    'debit' => $detail->qty * $detail->manufacturing_cost,
                    'credit' => 0,
                ];
            });

        $transactions = $transactions->merge($receivingDetails);

        // 7️⃣ Sale (main entry)
        $sales = SaleInvoice::with('account')
            ->where('account_id', $partyId)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->map(function($sale) {
                return [
                    'date' => $sale->date,
                    'type' => 'Sale Invoice',
                    'description' => 'Sale Invoice #' . $sale->id,
                    'debit' => 0,
                    'credit' => $sale->total_amount,
                ];
            });
        $transactions = $transactions->merge($sales);

        // 8️⃣ Sale Items
        $saleItems = SaleInvoiceItem::with('invoice')
            ->whereHas('invoice', function($q) use($partyId, $from, $to) {
                $q->where('account_id', $partyId)->whereBetween('date', [$from, $to]);
            })
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->sale->date,
                    'type' => 'Sale Item',
                    'description' => 'Sale Item of Invoice #' . $item->sale->id,
                    'debit' => 0,
                    'credit' => $item->qty * $item->rate,
                ];
            });
        $transactions = $transactions->merge($saleItems);

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
