<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseInvoice;
use App\Models\Product;
use App\Models\ChartOfAccounts; // assuming vendors are COA entries
use App\Models\ChartOfAccount;
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
        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'reference_no' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:1000',
            'total_amount' => 'required|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',

            // Validate each item row
            'item_id.*' => 'required|exists:products,id',
            'purchase_invoice_id.*' => 'required|exists:purchase_invoices,id',
            'quantity.*' => 'required|numeric|min:0',
            'unit_id.*' => 'required|exists:measurement_units,id',
            'price.*' => 'required|numeric|min:0',
            'amount.*' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Create Purchase Return
            $purchaseReturn = PurchaseReturn::create([
                'vendor_id' => $request->vendor_id,
                'return_date' => $request->return_date,
                'reference_no' => $request->reference_no,
                'remarks' => $request->remarks,
                'total_amount' => $request->total_amount,
                'net_amount' => $request->net_amount,
            ]);

            // Store Purchase Return Items
            foreach ($request->item_id as $index => $itemId) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'item_id' => $itemId,
                    'purchase_invoice_id' => $request->purchase_invoice_id[$index],
                    'quantity' => $request->quantity[$index],
                    'unit_id' => $request->unit_id[$index],
                    'price' => $request->price[$index],
                    'amount' => $request->amount[$index],
                ]);
            }

            DB::commit();
            return redirect()->route('purchase_return.index')->with('success', 'Purchase Return saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->withErrors(['error' => 'Failed to save: ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $return = PurchaseReturn::with('items')->findOrFail($id);
        $invoices = PurchaseInvoice::all();
        $products = Product::all();
        $units = MeasurementUnit::all();
        return view('purchase-returns.edit', compact('return', 'invoices', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'purchase_invoice_id' => 'required',
            'vendor_id' => 'required',
            'return_date' => 'required|date',
        ]);

        DB::transaction(function () use ($request, $id) {
            $return = PurchaseReturn::findOrFail($id);
            $return->update($request->only([
                'purchase_invoice_id', 'vendor_id', 'return_date', 'ref_no', 'remarks', 'total_amount', 'net_amount'
            ]));

            $return->items()->delete();

            foreach ($request->item_id as $index => $itemId) {
                $return->items()->create([
                    'item_id' => $itemId,
                    'item_name' => $request->item_name[$index],
                    'quantity' => $request->quantity[$index],
                    'price' => $request->price[$index],
                    'unit' => $request->unit[$index],
                    'amount' => $request->quantity[$index] * $request->price[$index],
                    'remarks' => $request->remarks[$index] ?? null,
                ]);
            }
        });

        return redirect()->route('purchase_return.index')->with('success', 'Purchase return updated successfully.');
    }
}
