<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ChartOfAccounts;
use App\Models\Product;

class ReportController extends Controller
{
    public function itemLedger(Request $request)
    {
        $items = Product::all();

        $itemId = $request->input('item_id');
        $from = $request->input('from_date');
        $to = $request->input('to_date');
        $ledger = [];

        if ($itemId && $from && $to) {
            $ledger = DB::select("
                SELECT * FROM (
                -- 1. Purchase Invoice Items (IN)
                SELECT 
                    pii.id AS ref_id,
                    'Purchase Invoice' AS type,
                    pi.invoice_date AS date,
                    pii.item_id,
                    CONCAT('Bill No: ', pi.bill_no) AS description,
                    pii.quantity AS qty_in,
                    0 AS qty_out
                FROM purchase_invoice_items pii
                INNER JOIN purchase_invoices pi ON pi.id = pii.purchase_invoice_id
                WHERE pii.item_id = ?

                UNION ALL

                    -- 2. Sale Invoice Items (OUT)
                    SELECT 
                        sii.id AS ref_id,
                        'Sale Invoice' AS type,
                        si.date AS date,
                        sii.product_id,
                        CONCAT('Invoice No: ', si.invoice_no) AS description,
                        0 AS qty_in,
                        sii.quantity AS qty_out
                    FROM sale_invoice_items sii
                    INNER JOIN sale_invoices si ON si.id = sii.sale_invoice_id
                    WHERE sii.product_id = ?

                    UNION ALL

                    -- 3. Purchase Return Items (OUT)
                    SELECT 
                        pri.id AS ref_id,
                        'Purchase Return' AS type,
                        pr.return_date AS date,
                        pri.item_id,
                        CONCAT('Return No: ', pr.reference_no) AS description,
                        0 AS qty_in,
                        pri.quantity AS qty_out
                    FROM purchase_return_items pri
                    INNER JOIN purchase_returns pr ON pr.id = pri.purchase_return_id
                    WHERE pri.item_id = ?

                    UNION ALL

                    -- 4. Production Raw Issue (OUT)
                    SELECT 
                        pd.id AS ref_id,
                        'Production Raw Issue' AS type,
                        p.order_date AS date,
                        pd.product_id,
                        'Raw Material Issued' AS description,
                        0 AS qty_in,
                        pd.qty AS qty_out
                    FROM production_details pd
                    INNER JOIN productions p ON p.id = pd.production_id
                    WHERE pd.product_id = ?

                    UNION ALL

                    -- 5. Production Receiving (IN)
                    SELECT 
                        prd.id AS ref_id,
                        'Production Receiving' AS type,
                        pr.rec_date AS date,
                        prd.product_id AS item_id,
                        'Manufactured Goods Received' AS description,
                        prd.received_qty AS qty_in,
                        0 AS qty_out
                    FROM production_receiving_details prd
                    INNER JOIN production_receivings pr ON pr.id = prd.production_receiving_id
                    WHERE prd.product_id = ?
                ) AS ledger
                WHERE date BETWEEN ? AND ?
                ORDER BY date ASC
            ", [
                $itemId, // for purchase_invoice_items
                $itemId, // for sale_invoice_items
                $itemId, // for purchase_return_items
                $itemId, // for production_details
                $itemId, // for production_receiving_details
                $from,
                $to
            ]);
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
