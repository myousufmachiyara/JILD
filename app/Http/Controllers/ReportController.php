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
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();

        $vendorId = $request->input('vendor_id');
        $from = $request->input('from_date');
        $to = $request->input('to_date');

        $ledger = [];

        if ($vendorId && $from && $to) {
           $ledger = DB::select("
            SELECT * FROM (
                -- 1. PURCHASE INVOICES
                SELECT
                    pi.id AS ref_id,
                    'Purchase Invoice' AS type,
                    pi.invoice_date AS date,
                    pi.vendor_id AS account_id,
                    CONCAT('Bill No: ', pi.bill_no) AS description,
                    0 AS debit,
                    (
                        IFNULL((SELECT SUM(quantity * price) FROM purchase_invoice_items WHERE purchase_invoice_id = pi.id), 0)
                        + pi.convance_charges + pi.labour_charges - pi.bill_discount
                    ) AS credit
                FROM purchase_invoices pi

                UNION ALL

                -- 2. PAYMENT VOUCHERS
                SELECT
                    pv.id AS ref_id,
                    'Payment Voucher' AS type,
                    pv.date,
                    pv.ac_cr_sid AS account_id,
                    pv.remarks AS description,
                    pv.amount AS debit,
                    0 AS credit
                FROM payment_vouchers pv

                UNION ALL

                -- 3. PURCHASE RETURNS
                SELECT
                    pr.id AS ref_id,
                    'Purchase Return' AS type,
                    pr.return_date AS date,
                    pr.vendor_id AS account_id,
                    CONCAT('Return No: ', pr.reference_no) AS description,
                    pr.net_amount AS debit,
                    0 AS credit
                FROM purchase_returns pr

                UNION ALL

                -- 4. PRODUCTION RAW ISSUE
                SELECT
                    pd.production_id AS ref_id,
                    'Production Raw Issue' AS type,
                    p.order_date AS date,
                    p.vendor_id AS account_id,
                    'Raw Material Issued for Production' AS description,
                    0 AS debit,
                    SUM(pd.qty * pd.rate) AS credit
                FROM production_details pd
                INNER JOIN productions p ON p.id = pd.production_id
                GROUP BY pd.production_id, p.order_date, p.vendor_id

                UNION ALL

                -- 5. PRODUCTION RECEIVING
                SELECT
                    pr.id AS ref_id,
                    'Production Receiving' AS type,
                    pr.rec_date AS date,
                    pr.vendor_id AS account_id,
                    'Manufacturing Cost for Received Goods' AS description,
                    SUM(prd.manufacturing_cost * prd.received_qty) AS debit,
                    0 AS credit
                FROM production_receivings pr
                INNER JOIN production_receiving_details prd ON pr.id = prd.production_receiving_id
                GROUP BY pr.id, pr.rec_date, pr.vendor_id
            ) AS ledger
            WHERE date BETWEEN :from AND :to
            AND account_id = :vendorId
            ORDER BY date ASC
            ", [
                'vendorId' => $vendorId,
                'from' => $from,
                'to' => $to
            ]);
        }

        return view('reports.party_ledger', compact('vendors', 'ledger', 'vendorId', 'from', 'to'));
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
