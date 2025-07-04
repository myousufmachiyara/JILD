<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\ProductionDetail;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductionController extends Controller
{
    public function index()
    {
        $productions = Production::with('vendor')->latest()->get();
        return view('production.index', compact('productions'));
    }

    public function create()
    {
        $vendors = \App\Models\ChartOfAccounts::where('account_type', 'vendor')->get();
        $categories = \App\Models\ProductCategory::all();
        $products = \App\Models\Product::all();
        return view('production.create', compact('vendors', 'categories', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'order_date' => 'required|date',
            'production_type' => 'required|in:1,2',
            'voucher_details.*.product_id' => 'required|exists:products,id',
            'voucher_details.*.qty' => 'required|numeric|min:0.01',
            'voucher_details.*.item_rate' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $production = Production::create([
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id,
                'order_date' => $request->order_date,
                'order_by' => Auth::id(),
                'production_type' => $request->production_type,
                'remarks' => $request->remarks,
                'net_amount' => $request->voucher_amount ?? 0,
            ]);

            foreach ($request->voucher_details as $detail) {
                ProductionDetail::create([
                    'production_id' => $production->id,
                    'product_id' => $detail['product_id'],
                    'qty' => $detail['qty'],
                    'rate' => $detail['item_rate'],
                    'total' => $detail['qty'] * $detail['item_rate'],
                ]);
            }

            DB::commit();
            return redirect()->route('productions.index')->with('success', 'Production order created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to save production. ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $production = Production::with(['vendor', 'details.product'])->findOrFail($id);
        return view('production.show', compact('production'));
    }    
}
