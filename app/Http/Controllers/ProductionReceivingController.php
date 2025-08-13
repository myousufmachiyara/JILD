<?php

namespace App\Http\Controllers;

use App\Models\ProductionReceiving;
use App\Models\ProductionReceivingDetail;
use App\Models\Production;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionReceivingController extends Controller
{
    public function index()
    {
        $receivings = ProductionReceiving::with(['vendor', 'production', 'details'])->orderBy('id', 'desc')->get();
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
        Log::info('Production Receiving Store Request', $request->all());

        $validated = $request->validate([
            'production_id' => 'required|exists:productions,id',
            'rec_date' => 'required|date',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.variation_id' => 'required|exists:product_variations,id',
            'item_details.*.received_qty' => 'required|numeric|min:0.01',
            'item_details.*.manufacturing_cost' => 'required|numeric|min:0',
            'item_details.*.remarks' => 'nullable|string',
            'convance_charges' => 'required|numeric|min:0',
            'bill_discount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $grn_no = 'GRN-' . str_pad(ProductionReceiving::count() + 1, 5, '0', STR_PAD_LEFT);

            $receiving = ProductionReceiving::create([
                'production_id' => $validated['production_id'],
                'rec_date' => $validated['rec_date'],
                'grn_no' => $grn_no,
                'convance_charges' => $validated['convance_charges'],
                'bill_discount' => $validated['bill_discount'],
                'received_by' => auth()->id(),
            ]);

            foreach ($validated['item_details'] as $detail) {            
                ProductionReceivingDetail::create([
                    'production_receiving_id' => $receiving->id,
                    'production_id' => $validated['production_id'],
                    'product_id' => $detail['product_id'],
                    'variation_id' => $detail['variation_id'],
                    'manufacturing_cost' => $detail['manufacturing_cost'],
                    'received_qty' => $detail['received_qty'],
                    'remarks' => $detail['remarks'] ?? null,
                ]);

            }

            DB::commit();

            return redirect()->route('production.receiving.index')
                ->with('success', 'Production receiving created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Production Receiving Store Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to save receiving. Please check logs.');
        }
    }

    public function edit($id)
    {
        $receiving = ProductionReceiving::with(['details.product', 'details.variation'])->findOrFail($id);
        $productions = Production::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $products = Product::where('item_type','fg')->get();

        return view('production-receiving.edit', compact('receiving', 'productions', 'vendors', 'products'));
    }

    public function update(Request $request, $id)
    {
        Log::info("Production Receiving Update Request: ", $request->all());

        $validated = $request->validate([
            'production_id' => 'required|exists:productions,id',
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'rec_date' => 'required|date',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.variation_id' => 'required|exists:product_variations,id',
            'item_details.*.received_qty' => 'required|numeric|min:0.01',
            'item_details.*.manufacturing_cost' => 'required|numeric|min:0',
            'item_details.*.profit_margin' => 'nullable|numeric|min:0',
            'item_details.*.remarks' => 'nullable|string',
            'convance_charges' => 'required|numeric|min:0',
            'bill_discount' => 'required|numeric|min:0',
            'net_amount' => 'required|numeric|min:0',
            'total_pcs' => 'required|numeric|min:0',
            'total_amt' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $receiving = ProductionReceiving::findOrFail($id);

            $receiving->update([
                'production_id' => $validated['production_id'],
                'vendor_id' => $validated['vendor_id'],
                'rec_date' => $validated['rec_date'],
                'convance_charges' => $validated['convance_charges'],
                'bill_discount' => $validated['bill_discount'],
                'net_amount' => $validated['net_amount'],
                'total_pcs' => $validated['total_pcs'],
                'total_amount' => $validated['total_amt'],
            ]);

            // Delete existing details
            ProductionReceivingDetail::where('production_receiving_id', $receiving->id)->delete();

            foreach ($validated['item_details'] as $detail) {
                $costPerPiece = $detail['manufacturing_cost'];
                $totalCost = $costPerPiece * $detail['received_qty'];
                $profitMargin = $detail['profit_margin'] ?? 30;
                $sellingPrice = $costPerPiece * (1 + ($profitMargin / 100));
                $barcode = 'PRD-' . $detail['product_id'] . '-' . $detail['variation_id'] . '-' . time() . '-' . rand(100, 999);

                ProductionReceivingDetail::create([
                    'production_receiving_id' => $receiving->id,
                    'production_id' => $validated['production_id'],
                    'product_id' => $detail['product_id'],
                    'variation_id' => $detail['variation_id'],
                    'manufacturing_cost' => $costPerPiece,
                    'received_qty' => $detail['received_qty'],
                    'total_unit_cost' => $costPerPiece,
                    'total' => $totalCost,
                    'total_cost' => $totalCost,
                    'profit_margin' => $profitMargin,
                    'selling_price' => $sellingPrice,
                    'barcode' => $barcode,
                    'remarks' => $detail['remarks'] ?? null,
                ]);
            }

            DB::commit();
            Log::info("ProductionReceiving #{$id} updated successfully.");

            return redirect()->route('production.receiving.index')
                ->with('success', 'Production receiving updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error("Production Receiving Update Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->withInput()->withErrors([
                'error' => 'Failed to update receiving. Please check logs.',
            ]);
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

    public function print($id)
    {
        $receiving = ProductionReceiving::with(['production', 'details.product', 'details.variation'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Production Receiving #' . $receiving->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $html = '
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 10px; }
            th, td { border: 1px solid #000; padding: 5px; font-size: 11px; }
            .header { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 10px; }
            .info-table td { border: none; font-size: 12px; }
        </style>

        <div class="header">Production Receiving</div>

        <table class="info-table">
            <tr>
                <td><strong>Receiving ID:</strong> ' . $receiving->id . '</td>
                <td><strong>Date:</strong> ' . $receiving->rec_date . '</td>
            </tr>
            <tr>
                <td><strong>Production:</strong> PROD-' . ($receiving->production->id ?? '-') . '</td>
                <td><strong>Challan No:</strong> ' . ($receiving->challan_no ?? '-') . '</td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th width="25%">Item Name</th>
                    <th width="30%">Variation SKU</th>
                    <th width="10%">M.Cost</th>
                    <th width="10%">Received</th>
                    <th width="20%">Remarks</th>
                </tr>
            </thead>
            <tbody>';

            foreach ($receiving->details as $i => $detail) {
                $html .= '
                <tr>
                    <td width="5%">' . ($i + 1) . '</td>
                    <td width="25%">' . ($detail->product->name ?? '-') . '</td>
                    <td width="30%">' . ($detail->variation->sku ?? '-') . '</td>
                    <td width="10%">' . ($detail->manufacturing_cost) . '</td>
                    <td width="10%">' . $detail->received_qty . '</td>
                    <td width="20%">' . ($detail->remarks ?? '-') . '</td>
                </tr>';
            }

        $html .= '</tbody></table>';

        $html .= '<br><br><strong>Total Items:</strong> ' . $receiving->details->count();

        if (!empty($receiving->remarks)) {
            $html .= '<br><br><strong>Remarks:</strong><br>' . nl2br($receiving->remarks);
        }

        $html .= '<br><br><br><strong>Authorized Signature: ____________________</strong>';

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('production_receiving_' . $receiving->id . '.pdf', 'I');
    }
}
