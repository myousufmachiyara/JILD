<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\ProductionDetail;
use App\Models\ProductCategory;
use \App\Models\ChartOfAccounts;
use \App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductionController extends Controller
{
    public function index()
    {
        $productions = Production::with(['vendor', 'category'])->orderBy('id', 'desc')->get();
        return view('production.index', compact('productions'));
    }

    public function create()
    {
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $categories = ProductCategory::all();
        $products = Product::all();

        $allProducts = $products->map(function ($product) {
        return [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
            ];
        })->values();
        
        return view('production.create', compact('vendors', 'categories', 'products', 'allProducts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'order_date' => 'required|date',
            'production_type' => 'required|in:cmt,sale_raw',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.qty' => 'required|numeric|min:0.01',
            'item_details.*.item_rate' => 'required|numeric|min:0',
            'item_details.*.item_unit' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $production = Production::create([
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id,
                'order_date' => $request->order_date,
                'created_by' => Auth::id(),
                'production_type' => $request->production_type,
                'remarks' => $request->remarks,
            ]);

            foreach ($request->item_details as $detail) {
                ProductionDetail::create([
                    'production_id' => $production->id,
                    'product_id' => $detail['product_id'],
                    'qty' => $detail['qty'],
                    'rate' => $detail['item_rate'],
                    'unit' => $detail['item_unit'],
                ]);
            }

            DB::commit();
            return redirect()->route('production.index')->with('success', 'Production order created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to save production. ' . $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $production = Production::with('details')->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $categories = ProductCategory::all();
        $products = Product::all();

        $allProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
            ];
        })->values();

        return view('production.edit', compact('production', 'vendors', 'categories', 'products', 'allProducts'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'order_date' => 'required|date',
            'production_type' => 'required|in:cmt,sale_raw',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.qty' => 'required|numeric|min:0.01',
            'item_details.*.item_rate' => 'required|numeric|min:0',
            'item_details.*.item_unit' => 'required',
        ]);

        DB::beginTransaction();

        try {
            $production = Production::findOrFail($id);
            $production->update([
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id,
                'order_date' => $request->order_date,
                'production_type' => $request->production_type,
                'remarks' => $request->remarks,
            ]);

            // Delete old items
            ProductionDetail::where('production_id', $production->id)->delete();

            foreach ($request->item_details as $detail) {
                ProductionDetail::create([
                    'production_id' => $production->id,
                    'product_id' => $detail['product_id'],
                    'qty' => $detail['qty'],
                    'rate' => $detail['item_rate'],
                    'unit' => $detail['item_unit'],
                ]);
            }

            DB::commit();
            return redirect()->route('production.index')->with('success', 'Production order updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to update production. ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $production = Production::with(['vendor', 'details.product'])->findOrFail($id);
        return view('production.show', compact('production'));
    }    
}
