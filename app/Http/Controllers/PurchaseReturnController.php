<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseInvoice;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use App\Models\MeasurementUnit;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $returns = PurchaseReturn::with('vendor')->latest()->get();
        return view('purchase-returns.index', compact('returns'));
    }

    public function create()
    {
        $invoices = PurchaseInvoice::with('vendor')->get();
        $products = Product::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();
        return view('purchase-returns.create', compact('invoices', 'products', 'units','vendors'));
    }

    public function store(Request $request)
    {
        // Log request input
        Log::info('Storing Purchase Return', ['request' => $request->all()]);

        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',

            // Validate each item row
            'item_id.*' => 'required|exists:products,id',
            'invoice_id.*' => 'required|exists:purchase_invoices,id',
            'quantity.*' => 'required|numeric|min:0',
            'unit.*' => 'required|exists:measurement_units,id',
            'price.*' => 'required|numeric|min:0',
            'amount.*' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $purchaseReturn = PurchaseReturn::create([
                'vendor_id'     => $request->vendor_id,
                'return_date'   => $request->return_date,
                'remarks'       => $request->remarks,
                'total_amount'  => $request->total_amount,
                'net_amount'    => $request->net_amount,
            ]);

            Log::info('Purchase Return created', ['id' => $purchaseReturn->id]);

            foreach ($request->item_id as $index => $itemId) {
                $itemData = [
                    'purchase_return_id'   => $purchaseReturn->id,
                    'item_id'              => $itemId,
                    'purchase_invoice_id'  => $request->invoice_id[$index],
                    'quantity'             => $request->quantity[$index],
                    'unit_id'              => $request->unit[$index],
                    'price'                => $request->price[$index],
                    'amount'               => $request->amount[$index],
                    'remarks'              => $request->remarks[$index] ?? null,
                ];

                PurchaseReturnItem::create($itemData);

                Log::info('Purchase Return Item created', ['data' => $itemData]);
            }

            DB::commit();
            Log::info('Purchase Return transaction committed successfully.');

            return redirect()->route('purchase_return.index')->with('success', 'Purchase Return saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase Return store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->withErrors(['error' => 'Failed to save: ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $purchaseReturn = PurchaseReturn::with('items')->findOrFail($id);
        $products = Product::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();
        $invoices = PurchaseInvoice::with('vendor')->get();

        return view('purchase-returns.edit', compact('purchaseReturn', 'products', 'vendors', 'units', 'invoices'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'remarks' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',

            'item_id.*' => 'required|exists:products,id',
            'invoice_id.*' => 'required|exists:purchase_invoices,id',
            'quantity.*' => 'required|numeric|min:0',
            'unit.*' => 'required|exists:measurement_units,id',
            'price.*' => 'required|numeric|min:0',
            'amount.*' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $purchaseReturn = PurchaseReturn::findOrFail($id);
            $purchaseReturn->update([
                'vendor_id' => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks' => $request->remarks,
                'total_amount' => $request->total_amount,
                'net_amount' => $request->net_amount,
            ]);

            // Remove existing items
            PurchaseReturnItem::where('purchase_return_id', $purchaseReturn->id)->delete();

            // Add updated items
            foreach ($request->item_id as $index => $itemId) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'item_id' => $itemId,
                    'purchase_invoice_id' => $request->invoice_id[$index],
                    'quantity' => $request->quantity[$index],
                    'unit_id' => $request->unit[$index],
                    'price' => $request->price[$index],
                    'amount' => $request->amount[$index],
                    'remarks' => $request->remarks[$index] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('purchase_return.index')->with('success', 'Purchase Return updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to update: ' . $e->getMessage()]);
        }
    }

    public function print($id)
    {
        $return = PurchaseReturn::with(['vendor', 'items.item', 'items.unit', 'items.invoice'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Purchase Return #' . $return->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $html = '
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 10px; }
            th, td { border: 1px solid #000; padding: 5px; font-size: 11px; }
            .header { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 10px; }
            .info-table td { border: none; font-size: 12px; }
            .summary-table td { font-size: 12px; }
        </style>

        <div class="header">Purchase Return</div>
        <br><br>';

        $html .= '
        <table class="info-table">
            <tr>
                <td><strong>Return No:</strong> ' . $return->id . '</td>
                <td><strong>Date:</strong> ' . $return->return_date . '</td>
            </tr>
            <tr>
                <td><strong>Vendor:</strong> ' . ($return->vendor->name ?? '-') . '</td>
                <td></td>
            </tr>
        </table>
        <br><br>';

        $html .= '
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Code</th>
                    <th>Item Name</th>
                    <th>Invoice #</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Rate</th>
                    <th>Amount</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>';

        $totalQty = 0;
        $totalAmount = 0;

        foreach ($return->items as $i => $item) {
            $totalQty += $item->quantity;
            $totalAmount += $item->amount;

            $html .= '
                <tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . ($item->item->item_code ?? '-') . '</td>
                    <td>' . ($item->item->name ?? '-') . '</td>
                    <td>' . ($item->invoice->id ?? '-') . '</td>
                    <td>' . $item->quantity . '</td>
                    <td>' . ($item->unit->name ?? '-') . '</td>
                    <td>' . number_format($item->price, 2) . '</td>
                    <td>' . number_format($item->amount, 2) . '</td>
                    <td>' . ($item->remarks ?? '-') . '</td>
                </tr>';
        }

        $html .= '</tbody></table><br><br>';

        // Totals and Summary Section
        $html .= '
        <table class="summary-table">
            <tr>
                <td><strong>Total Quantity:</strong></td>
                <td>' . number_format($totalQty, 2) . '</td>
            </tr>
            <tr>
                <td><strong>Total Amount:</strong></td>
                <td>' . number_format($totalAmount, 2) . '</td>
            </tr>
            <tr>
                <td><strong>Net Amount:</strong></td>
                <td>' . number_format($return->net_amount, 2) . '</td>
            </tr>
        </table>
        <br><br>';

        if (!empty($return->remarks)) {
            $html .= '<strong>Remarks:</strong><br>' . nl2br($return->remarks) . '<br><br>';
        }

        $html .= '<br><br><strong>Authorized Signature: ____________________</strong>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('purchase_return_' . $return->id . '.pdf', 'I');
    }

}
