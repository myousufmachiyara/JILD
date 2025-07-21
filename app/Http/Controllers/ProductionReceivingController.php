<?php

namespace App\Http\Controllers;

use App\Models\ProductionReceiving;
use App\Models\ProductionReceivingDetail;
use App\Models\Production;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionReceivingController extends Controller
{
    public function index()
    {
        $receivings = ProductionReceiving::with('vendor', 'production')->orderBy('id', 'desc')->get();
        return view('production-receiving.index', compact('receivings'));
    }

    public function create()
    {
        $productions = Production::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::where('item_type','fg')->get();
        return view('production-receiving.create', compact('productions', 'vendors', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'production_id' => 'required|exists:productions,id',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'rec_date' => 'required|date',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.variation' => 'nullable|string',
            'item_details.*.received_qty' => 'required|numeric|min:0.01',
            'item_details.*.manufacturing_cost' => 'required|numeric|min:0',
            'item_details.*.remarks' => 'nullable|string',
            'convance_charges' => 'required|numeric|min:0',
            'bill_discount' => 'required|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',
            'total_pcs' => 'required|numeric|min:0',
            'total_amt' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Generate GRN number
            $grn_no = 'GRN-' . str_pad(ProductionReceiving::count() + 1, 5, '0', STR_PAD_LEFT);

            // Create main receiving record
            $receiving = ProductionReceiving::create([
                'production_id' => $validated['production_id'],
                'vendor_id' => $validated['vendor_id'],
                'rec_date' => $validated['rec_date'],
                'grn_no' => $grn_no,
                'convance_charges' => $validated['convance_charges'],
                'bill_discount' => $validated['bill_discount'],
                'net_amount' => $validated['net_amount'],
                'total_pcs' => $validated['total_pcs'],
                'total_amount' => $validated['total_amt'],
                'received_by' => auth()->id(), // Assuming you have authentication
            ]);

            // Create receiving details
            foreach ($validated['item_details'] as $detail) {
                ProductionReceivingDetail::create([
                    'production_receiving_id' => $receiving->id,
                    'product_id' => $detail['product_id'],
                    'variation' => $detail['variation'] ?? null,
                    'manufacturing_cost' => $detail['manufacturing_cost'],
                    'received_qty' => $detail['received_qty'],
                    'remarks' => $detail['remarks'] ?? null,
                    'total' => $detail['manufacturing_cost'] * $detail['received_qty'],
                ]);

                // Optional: Update stock/inventory here if needed
                // Inventory::updateOrCreate(...);
            }

            DB::commit();
            return redirect()->route('production.receivings.index')
                ->with('success', 'Production receiving created successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to save receiving: ' . $e->getMessage());
        }
    }

    public function getVariations($id)
    {
        $product = Product::with('variations')->findOrFail($id);
        
        $variations = $product->variations->map(function ($variation) use ($product) {
            return [
                'id' => $variation->id,
                'sku' => $variation->sku,
                'manufacturing_cost' => $product->manufacturing_cost
            ];
        });

        return response()->json($variations);
    }
}
