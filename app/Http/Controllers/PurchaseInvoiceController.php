<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceAttachment;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\ChartOfAccounts; // assuming vendors are COA entries
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\myPDF;
use Carbon\Carbon;

class PurchaseInvoiceController extends Controller
{
    public function index()
    {
        $invoices = PurchaseInvoice::with('vendor')->latest()->get();
        return view('purchases.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->get();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();

        return view('purchases.create', compact('products', 'vendors','units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_date' => 'required|date',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'payment_terms' => 'nullable|string',
            'bill_no' => 'nullable|string|max:100',
            'ref_no' => 'nullable|string|max:100',
            'remarks' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,zip|max:2048',

            'item_cod.*' => 'nullable|string',
            'item_name.*' => 'required|exists:products,id',
            'bundle.*' => 'nullable|numeric',
            'quantity.*' => 'required|numeric',
            'unit.*' => 'nullable|string|max:50',
            'price.*' => 'required|numeric',

            'convance_charges' => 'nullable|numeric',
            'labour_charges' => 'nullable|numeric',
            'bill_discount' => 'nullable|numeric',
        ]);

        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice::create([
                'vendor_id' => $request->vendor_id,
                'invoice_date' => $request->invoice_date,
                'payment_terms' => $request->payment_terms,
                'bill_no' => $request->bill_no,
                'ref_no' => $request->ref_no,
                'remarks' => $request->remarks,
                'convance_charges' => $request->convance_charges ?? 0,
                'labour_charges' => $request->labour_charges ?? 0,
                'bill_discount' => $request->bill_discount ?? 0,
                'created_by' => auth()->id(),
            ]);

            // Save item rows
            foreach ($request->item_name as $index => $product_id) {
                $invoice->items()->create([
                    'item_id' => $product_id,
                    'item_name' => Product::find($product_id)->name,
                    'item_code' => $request->item_cod[$index] ?? null,
                    'bundle' => $request->bundle[$index] ?? 0,
                    'quantity' => $request->quantity[$index] ?? 0,
                    'unit' => $request->unit[$index] ?? '',
                    'price' => $request->price[$index] ?? 0,
                    'amount' => ($request->price[$index] ?? 0) * ($request->quantity[$index] ?? 0),
                ]);
            }

            // Handle attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('purchase_invoices', 'public');
                    $invoice->attachments()->create([
                        'file_path' => $path,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('purchase_invoices.index')->with('success', 'Purchase Invoice created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase Invoice Store Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['error' => 'Failed to create invoice. Please try again.']);
        }
    }

    public function edit($id)
    {
        $invoice = PurchaseInvoice::with(['items', 'attachments'])->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->get();
        $units = MeasurementUnit::all(); // <-- add this line

        return view('purchases.edit', compact('invoice', 'vendors', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'invoice_date' => 'required|date',
            'pur_qty.*' => 'required|numeric|min:0.01',
            'pur_price.*' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice::findOrFail($id);

            $invoice->update([
                'vendor_id' => $request->vendor_id,
                'invoice_date' => $request->invoice_date,
                'payment_terms' => $request->payment_terms,
                'bill_no' => $request->bill_no,
                'ref_no' => $request->ref_no,
                'remarks' => $request->remarks,
                'convance_charges' => $request->convance_charges ?? 0,
                'labour_charges' => $request->labour_charges ?? 0,
                'bill_discount' => $request->bill_discount ?? 0,
            ]);

            Log::info('[PurchaseInvoice Update] Invoice updated', [
                'invoice_id' => $invoice->id,
                'user_id' => auth()->id(),
            ]);

            // Delete old items
            $invoice->items()->delete();
            Log::info('[PurchaseInvoice Update] Old items deleted', [
                'invoice_id' => $invoice->id
            ]);

            // Insert updated items
            foreach ($request->item_cod as $index => $code) {
                $item = PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'item_id' => $request->item_name[$index], // actual product ID
                    'item_name' => $code ?? null,              // this could be item code
                    'bundle' => $request->bundle[$index] ?? 0,
                    'quantity' => $request->quantity[$index],
                    'unit' => $request->unit[$index] ?? null,
                    'price' => $request->price[$index],
                    'remarks' => $request->unit[$index] ?? null,
                ]);

                Log::info('[PurchaseInvoice Update] Item added', [
                    'invoice_id' => $invoice->id,
                    'item_id' => $item->id,
                ]);
            }

            // Add new attachments if any
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('purchase_invoices', $fileName, 'public');

                    $invoice->attachments()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                    ]);

                    Log::info('[PurchaseInvoice Update] Attachment uploaded', [
                        'invoice_id' => $invoice->id,
                        'file' => $fileName,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('purchase_invoices.index')->with('success', 'Purchase Invoice updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('[PurchaseInvoice Update] Update failed', [
                'invoice_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()->withErrors(['error' => 'Update failed. ' . $e->getMessage()]);
        }
    }

    public function destroy($id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);

        // Delete attached files from storage
        foreach ($invoice->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->file_path);
        }

        $invoice->delete();

        return redirect()->route('purchase_invoices.index')->with('success', 'Purchase Invoice deleted successfully.');
    }

    public function getInvoicesByItem($itemId)
    {
        $invoices = PurchaseInvoice::whereHas('items', function ($q) use ($itemId) {
            $q->where('item_id', $itemId);
        })
        ->with('vendor')
        ->get(['id', 'vendor_id']);

        return response()->json(
            $invoices->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'vendor' => $inv->vendor->name ?? '',
                ];
            })
        );
    }

    public function getItemDetails($invoiceId, $itemId)
    {
        $item = PurchaseInvoiceItem::with(['product', 'unit'])
            ->where('purchase_invoice_id', $invoiceId)
            ->where('item_id', $itemId)
            ->first();

        if (!$item) {
            return response()->json(['error' => 'Item not found in this invoice.'], 404);
        }

        return response()->json([
            'item_id'   => $item->item_id,
            'item_name' => $item->product->name ?? '',
            'quantity'  => $item->quantity,
            'unit_id'   => $item->unit_id,
            'unit_name' => $item->unit->name ?? '',
            'price'     => $item->price,
        ]);
    }

    public function print($id)
    {
        $invoice = PurchaseInvoice::with(['vendor', 'items.product', 'items.unit'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Purchase Invoice #' . $invoice->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $html = '
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 10px; }
            th, td { border: 1px solid #000; padding: 5px; font-size: 11px; }
            .header { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 10px; }
            .info-table td { border: none; font-size: 12px; }
        </style>

        <div class="header">Purchase Invoice</div>

        <table class="info-table">
            <tr>
                <td><strong>Invoice No:</strong> ' . $invoice->id . '</td>
                <td><strong>Date:</strong> ' . $invoice->date . '</td>
            </tr>
            <tr>
                <td><strong>Vendor:</strong> ' . ($invoice->vendor->name ?? '') . '</td>
                <td><strong>Bill No:</strong> ' . ($invoice->bill_no ?? '-') . '</td>
            </tr>
            <tr>
                <td><strong>Ref No:</strong> ' . ($invoice->ref_no ?? '-') . '</td>
                <td><strong>Payment Terms:</strong> ' . ($invoice->payment_terms ?? '-') . '</td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Code</th>
                    <th>Item Name</th>
                    <th>Bundle</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Rate</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($invoice->items as $i => $item) {
            $html .= '
                <tr>
                    <td>' . ($i + 1) . '</td>
                    <td>' . ($item->item->item_code ?? '-') . '</td>
                    <td>' . ($item->item->name ?? '-') . '</td>
                    <td>' . $item->bundle . '</td>
                    <td>' . $item->quantity . '</td>
                    <td>' . ($item->unit->name ?? '-') . '</td>
                    <td>' . number_format($item->price, 2) . '</td>
                    <td>' . $item->remarks . '</td>
                </tr>';
        }

        $html .= '</tbody></table>';

        $html .= '<br><br><strong>Total Items:</strong> ' . $invoice->items->count();

        if (!empty($invoice->charges) || !empty($invoice->discount)) {
            $html .= '<br><strong>Additional Charges:</strong> ' . number_format($invoice->charges, 2);
            $html .= '<br><strong>Discount:</strong> ' . number_format($invoice->discount, 2);
        }

        if (!empty($invoice->remarks)) {
            $html .= '<br><br><strong>Remarks:</strong><br>' . nl2br($invoice->remarks);
        }

        $html .= '<br><br><br><strong>Authorized Signature: ____________________</strong>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('purchase_invoice_' . $invoice->id . '.pdf', 'I');

    }

}
