<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function index()
    {
        $returns = PurchaseReturn::with('vendor')->latest()->get();
        return view('purchase-returns.index', compact('returns'));
    }

    public function create()
    {
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::all();
        $units = MeasurementUnit::all();
        return view('purchase-returns.create', compact('vendors', 'products', 'units'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor_id' => 'required',
            'return_date' => 'required|date',
            'reference_no' => 'nullable|string',
            'remarks' => 'nullable|string',
            'items' => 'required|array',
            'items.*.item_id' => 'required',
            'items.*.quantity' => 'required|numeric',
            'items.*.unit_id' => 'required',
            'items.*.price' => 'required|numeric',
        ]);

        DB::transaction(function () use ($data, $request) {
            $total = 0;
            foreach ($request->items as $item) {
                $total += $item['quantity'] * $item['price'];
            }

            $return = PurchaseReturn::create([
                'vendor_id' => $request->vendor_id,
                'return_date' => $request->return_date,
                'reference_no' => $request->reference_no,
                'remarks' => $request->remarks,
                'total_amount' => $total,
                'net_amount' => $total
            ]);

            foreach ($request->items as $item) {
                $return->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'],
                    'price' => $item['price'],
                    'amount' => $item['quantity'] * $item['price']
                ]);
            }
        });

        return redirect()->route('purchase_returns.index')->with('success', 'Purchase return created.');
    }

    public function edit($id)
    {
        $return = PurchaseReturn::with('items')->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::all();
        $units = MeasurementUnit::all();
        return view('purchase-returns.edit', compact('return', 'vendors', 'products', 'units'));
    }

    public function update(Request $request, $id)
    {
        $return = PurchaseReturn::with('items')->findOrFail($id);

        $data = $request->validate([
            'vendor_id' => 'required',
            'return_date' => 'required|date',
            'reference_no' => 'nullable|string',
            'remarks' => 'nullable|string',
            'items' => 'required|array',
            'items.*.item_id' => 'required',
            'items.*.quantity' => 'required|numeric',
            'items.*.unit_id' => 'required',
            'items.*.price' => 'required|numeric',
        ]);

        DB::transaction(function () use ($request, $return) {
            $total = 0;
            foreach ($request->items as $item) {
                $total += $item['quantity'] * $item['price'];
            }

            $return->update([
                'vendor_id' => $request->vendor_id,
                'return_date' => $request->return_date,
                'reference_no' => $request->reference_no,
                'remarks' => $request->remarks,
                'total_amount' => $total,
                'net_amount' => $total
            ]);

            $return->items()->delete();

            foreach ($request->items as $item) {
                $return->items()->create([
                    'item_id' => $item['item_id'],
                    'quantity' => $item['quantity'],
                    'unit_id' => $item['unit_id'],
                    'price' => $item['price'],
                    'amount' => $item['quantity'] * $item['price']
                ]);
            }
        });

        return redirect()->route('purchase_returns.index')->with('success', 'Purchase return updated.');
    }

    public function destroy($id)
    {
        $return = PurchaseReturn::findOrFail($id);
        $return->items()->delete();
        $return->delete();
        return redirect()->route('purchase_returns.index')->with('success', 'Purchase return deleted.');
    }
}
