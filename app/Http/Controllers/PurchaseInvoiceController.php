<?php

namespace App\Http\Controllers;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseInvoiceAttachment;
use App\Models\Product;
use App\Models\ChartOfAccounts; // assuming vendors are COA entries
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurchaseInvoiceController extends Controller
{
    public function index()
    {
        $invoices = PurchaseInvoice::with('vendor')->latest()->get();
        return view('purchases.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::select('id', 'name')->get();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();

        return view('purchases.create', compact('products', 'vendors'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'invoice_date' => 'required|date',
            'pur_qty.*' => 'required|numeric|min:0.01',
            'pur_price.*' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice = PurchaseInvoice::create([
                'vendor_id' => $request->vendor_id,
                'invoice_date' => $request->pur_date,
                'payment_terms' => $request->pur_bill_no,
                'bill_no' => $request->pur_sale_inv,
                'ref_no' => $request->ref_no,
                'remarks' => $request->pur_remarks,
                'convance_charges' => $request->pur_convance_char ?? 0,
                'labour_charges' => $request->pur_labor_char ?? 0,
                'bill_discount' => $request->bill_discount ?? 0,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->item_cod as $index => $code) {
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'item_id' => $code,
                    'item_name' => $request->item_name[$index] ?? null,
                    'bundle' => $request->pur_qty2[$index] ?? 0,
                    'quantity' => $request->pur_qty[$index],
                    'unit' => $request->remarks[$index] ?? null,
                    'price' => $request->pur_price[$index],
                    'remarks' => $request->remarks[$index] ?? null,
                ]);
            }

            if ($request->hasFile('att')) {
                foreach ($request->file('att') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('purchase_invoices', $fileName, 'public');

                    $invoice->attachments()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('purchases.index')->with('success', 'Purchase Invoice created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to create invoice. ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $invoice = PurchaseInvoice::with(['items', 'attachments'])->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        return view('purchases.edit', compact('invoice', 'vendors'));
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
                'invoice_date' => $request->pur_date,
                'payment_terms' => $request->pur_bill_no,
                'bill_no' => $request->pur_sale_inv,
                'ref_no' => $request->ref_no,
                'remarks' => $request->pur_remarks,
                'convance_charges' => $request->pur_convance_char ?? 0,
                'labour_charges' => $request->pur_labor_char ?? 0,
                'bill_discount' => $request->bill_discount ?? 0,
            ]);

            // Delete old items
            $invoice->items()->delete();

            // Insert updated items
            foreach ($request->item_cod as $index => $code) {
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'item_id' => $code,
                    'item_name' => $request->item_name[$index] ?? null,
                    'bundle' => $request->pur_qty2[$index] ?? 0,
                    'quantity' => $request->pur_qty[$index],
                    'unit' => $request->remarks[$index] ?? null,
                    'price' => $request->pur_price[$index],
                    'remarks' => $request->remarks[$index] ?? null,
                ]);
            }

            // Add new attachments if any
            if ($request->hasFile('att')) {
                foreach ($request->file('att') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('purchase_invoices', $fileName, 'public');

                    $invoice->attachments()->create([
                        'file_path' => $path,
                        'file_name' => $file->getClientOriginalName(),
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('purchases.index')->with('success', 'Purchase Invoice updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
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

        return redirect()->route('purchases.index')->with('success', 'Purchase Invoice deleted successfully.');
    }
}
