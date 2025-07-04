<?php

namespace App\Http\Controllers;

use App\Models\ProductionReceiving;
use App\Models\ProductionReceivingDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ProductionReceivingController extends Controller
{
    public function index()
    {
        $receivings = ProductionReceiving::with('production.vendor')->latest()->get();
        return view('production_receiving.index', compact('receivings'));
    }

    public function create()
    {
        $productions = Production::with('vendor')->get();
        return view('production_receiving.create', compact('productions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'production_id' => 'required|exists:productions,id',
            'receiving_date' => 'required|date',
            'receiving_details.*.product_id' => 'required|exists:products,id',
            'receiving_details.*.used_qty' => 'required|numeric|min:0',
            'receiving_details.*.waste_qty' => 'required|numeric|min:0',
            'receiving_details.*.missed_qty' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $receiving = ProductionReceiving::create([
                'production_id' => $request->production_id,
                'receiving_date' => $request->receiving_date,
                'received_by' => Auth::id(),
                'remarks' => $request->remarks,
            ]);

            foreach ($request->receiving_details as $detail) {
                ProductionReceivingDetail::create([
                    'production_receiving_id' => $receiving->id,
                    'product_id' => $detail['product_id'],
                    'used_qty' => $detail['used_qty'],
                    'waste_qty' => $detail['waste_qty'],
                    'missed_qty' => $detail['missed_qty'],
                    'remarks' => $detail['remarks'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('production-receiving.index')->with('success', 'Production receiving saved successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Failed to save receiving. ' . $e->getMessage()]);
        }
    }

    public function show($id)
    {
        $receiving = ProductionReceiving::with(['production.vendor', 'details.product'])->findOrFail($id);
        return view('production_receiving.show', compact('receiving'));
    }
}
