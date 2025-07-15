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
        $products = Product::all();
        return view('production-receiving.create', compact('productions', 'vendors', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'production_id' => 'required|exists:productions,id',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'rec_date' => 'required|date',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.received_qty' => 'required|numeric|min:0.01',
            'item_details.*.manufacturing_cost' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $grn_no = 'GRN-' . str_pad(ProductionReceiving::count() + 1, 5, '0', STR_PAD_LEFT);

            $receiving = ProductionReceiving::create([
                'production_id' => $request->production_id,
                'vendor_id' => $request->vendor_id,
                'rec_date' => $request->rec_date,
                'grn_no' => $grn_no,
                'convance_charges' => $request->pur_convance_char ?? 0,
                'bill_discount' => $request->bill_discount ?? 0,
                'net_amount' => $request->net_amount ?? 0,
            ]);

            foreach ($request->item_details as $detail) {
                ProductionReceivingDetail::create([
                    'production_receiving_id' => $receiving->id,
                    'product_id' => $detail['product_id'],
                    'variation' => $detail['variation'] ?? null,
                    'manufacturing_cost' => $detail['manufacturing_cost'],
                    'received_qty' => $detail['received_qty'],
                    'remarks' => $detail['remarks'] ?? null,
                    'total' => $detail['manufacturing_cost'] * $detail['received_qty'],
                ]);
            }

            DB::commit();
            return redirect()->route('production.receivings.index')->with('success', 'Receiving saved successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors(['error' => 'Failed to save receiving: ' . $e->getMessage()]);
        }
    }

    public function getVariations($id)
    {
        $product = Product::with('variations')->findOrFail($id);

        $variations = $product->variations->map(function ($variation) {
            return [
                'id' => $variation->id,
                'name' => $variation->name,
                'manufacturing_cost' => $variation->manufacturing_cost,
            ];
        });

        return response()->json($variations);
    }
}
