<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\SaleInvoiceAttachment;
use App\Models\ChartOfAccount;
use App\Models\ChartOfAccountTransaction;
use App\Models\Product;
use App\Models\MeasurementUnit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SaleController extends Controller
{
    public function index()
    {
        $invoices = SaleInvoice::with('customer')->latest()->get();
        return view('sale_invoices.index', compact('invoices'));
    }

    public function create()
    {
        $products = Product::all();
        $units = MeasurementUnit::all();
        $customers = ChartOfAccount::where('type', 'customer')->get();
        return view('sale_invoices.create', compact('products', 'units', 'customers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'coa_id' => 'required|exists:chart_of_accounts,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice = SaleInvoice::create([
                'coa_id' => $request->coa_id,
                'date' => $request->date,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
            ]);

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $amount = $item['quantity'] * $item['price'];
                $totalAmount += $amount;

                SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'price' => $item['price'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('sale_invoices', 'public');
                    SaleInvoiceAttachment::create([
                        'sale_invoice_id' => $invoice->id,
                        'path' => $path,
                    ]);
                }
            }

            ChartOfAccountTransaction::create([
                'coa_id' => $request->coa_id,
                'date' => $request->date,
                'type' => 'Sale Invoice',
                'voucher_type' => 'sale_invoice',
                'voucher_id' => $invoice->id,
                'description' => 'Sale Invoice #' . $invoice->id,
                'debit' => $totalAmount,
                'credit' => 0,
            ]);

            DB::commit();

            return redirect()->route('sale_invoices.index')->with('success', 'Sale Invoice created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Failed to create invoice: ' . $e->getMessage()])->withInput();
        }
    }

    public function edit($id)
    {
        $invoice = SaleInvoice::with(['items', 'attachments'])->findOrFail($id);
        $products = Product::all();
        $units = MeasurementUnit::all();
        $customers = ChartOfAccount::where('type', 'customer')->get();
        return view('sale_invoices.edit', compact('invoice', 'products', 'units', 'customers'));
    }

    public function update(Request $request, $id)
    {
        $invoice = SaleInvoice::findOrFail($id);

        $request->validate([
            'coa_id' => 'required|exists:chart_of_accounts,id',
            'date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $invoice->update([
                'coa_id' => $request->coa_id,
                'date' => $request->date,
                'remarks' => $request->remarks,
            ]);

            $invoice->items()->delete();
            $invoice->attachments()->delete();
            ChartOfAccountTransaction::where('voucher_type', 'sale_invoice')->where('voucher_id', $invoice->id)->delete();

            $totalAmount = 0;
            foreach ($request->items as $item) {
                $amount = $item['quantity'] * $item['price'];
                $totalAmount += $amount;

                SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'price' => $item['price'],
                    'remarks' => $item['remarks'] ?? null,
                ]);
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('sale_invoices', 'public');
                    SaleInvoiceAttachment::create([
                        'sale_invoice_id' => $invoice->id,
                        'path' => $path,
                    ]);
                }
            }

            ChartOfAccountTransaction::create([
                'coa_id' => $request->coa_id,
                'date' => $request->date,
                'type' => 'Sale Invoice',
                'voucher_type' => 'sale_invoice',
                'voucher_id' => $invoice->id,
                'description' => 'Sale Invoice #' . $invoice->id,
                'debit' => $totalAmount,
                'credit' => 0,
            ]);

            DB::commit();

            return redirect()->route('sale_invoices.index')->with('success', 'Sale Invoice updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Failed to update invoice: ' . $e->getMessage()])->withInput();
        }
    }

    public function destroy($id)
    {
        $invoice = SaleInvoice::findOrFail($id);

        DB::beginTransaction();
        try {
            $invoice->items()->delete();
            $invoice->attachments()->delete();
            ChartOfAccountTransaction::where('voucher_type', 'sale_invoice')->where('voucher_id', $invoice->id)->delete();
            $invoice->delete();
            DB::commit();
            return redirect()->route('sale_invoices.index')->with('success', 'Sale Invoice deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Failed to delete invoice: ' . $e->getMessage()]);
        }
    }
}
