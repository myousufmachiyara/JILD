<?php

namespace App\Http\Controllers;

use App\Models\StockTransfer;
use App\Models\StockTransferDetail;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockTransferController extends Controller
{
    /**
     * Display a listing of stock transfers.
     */
    public function index()
    {
        try {
            $transfers = StockTransfer::with(['fromLocation', 'toLocation', 'details.product', 'details.variation'])
                ->orderBy('date', 'desc')
                ->get();

            return view('stock_transfers.index', compact('transfers'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to load stock transfers: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for creating a new stock transfer.
     */
    public function create()
    {
        try {
            $products = Product::with('variations')->get();
            $locations = Location::all();

            return view('stock_transfers.create', compact('products', 'locations'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to load form: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created stock transfer in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::create([
                'date' => $request->date,
                'from_location_id' => $request->from_location_id,
                'to_location_id' => $request->to_location_id,
                'remarks' => $request->remarks,
                'created_by' => Auth::id(),
            ]);

            foreach ($request->items as $item) {
                StockTransferDetail::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();
            return redirect()->route('stock_transfers.index')->with('success', 'Stock transfer created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create stock transfer: ' . $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified stock transfer.
     */
    public function edit($id)
    {
        try {
            $transfer = StockTransfer::with('details')->findOrFail($id);
            $products = Product::with('variations')->get();
            $locations = Location::all();

            return view('stock_transfers.edit', compact('transfer', 'products', 'locations'));
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to load stock transfer: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified stock transfer in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'date' => 'required|date',
            'from_location_id' => 'required|exists:locations,id',
            'to_location_id' => 'required|exists:locations,id|different:from_location_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
        ]);

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::findOrFail($id);
            $transfer->update([
                'date' => $request->date,
                'from_location_id' => $request->from_location_id,
                'to_location_id' => $request->to_location_id,
                'remarks' => $request->remarks,
            ]);

            // Delete existing details and recreate
            $transfer->details()->delete();

            foreach ($request->items as $item) {
                StockTransferDetail::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();
            return redirect()->route('stock_transfers.index')->with('success', 'Stock transfer updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to update stock transfer: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified stock transfer from storage.
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $transfer = StockTransfer::findOrFail($id);
            $transfer->details()->delete();
            $transfer->delete();

            DB::commit();
            return redirect()->route('stock_transfers.index')->with('success', 'Stock transfer deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to delete stock transfer: ' . $e->getMessage());
        }
    }

    /**
     * Optional: fetch variation by product for dynamic selects (used in Blade JS)
     */
    public function getProductVariations($productId)
    {
        try {
            $variations = ProductVariation::where('product_id', $productId)->get(['id', 'sku']);
            return response()->json($variations);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    /**
     * Optional: fetch product variation by barcode (used in Blade JS)
     */
    public function getVariationByCode($code)
    {
        try {
            $variation = ProductVariation::with('product')
                ->where('sku', $code)
                ->first();

            if (!$variation) {
                return response()->json(['success' => false, 'message' => 'No variation found']);
            }

            return response()->json(['success' => true, 'variation' => $variation]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching variation']);
        }
    }

    public function print($id)
    {
        $transfer = StockTransfer::with(['fromLocation', 'toLocation', 'details.product', 'details.variation'])
            ->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Stock Transfer #' . $transfer->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/Jild-Logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        // --- Transfer Info Box ---
        $pdf->SetXY(130, 12);
        $transferInfo = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Transfer #</b></td><td>' . $transfer->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($transfer->date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>From</b></td><td>' . ($transfer->fromLocation->name ?? '-') . '</td></tr>
            <tr><td><b>To</b></td><td>' . ($transfer->toLocation->name ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($transferInfo, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // --- Title Box ---
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Stock Transfer', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        // --- Items Table ---
        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="35%">Product</th>
                <th width="28%">Variation</th>
                <th width="15%">Quantity</th>
            </tr>';

        $count = 0;
        foreach ($transfer->details as $item) {
            $count++;
            $html .= '
            <tr>
                <td align="center">' . $count . '</td>
                <td>' . ($item->product->name ?? '-') . '</td>
                <td>' . ($item->variation->sku ?? '-') . '</td>
                <td align="center">' . number_format($item->quantity, 2) . '</td>
            </tr>';
        }

        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Remarks ---
        if (!empty($transfer->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($transfer->remarks) . '</span>';
            $pdf->writeHTML($remarksHtml, true, false, true, false, '');
        }

        // --- Signatures ---
        $pdf->Ln(20);
        $yPos = $pdf->GetY();
        $lineWidth = 40;

        $pdf->Line(28, $yPos, 28 + $lineWidth, $yPos);
        $pdf->Line(130, $yPos, 130 + $lineWidth, $yPos);

        $pdf->SetXY(28, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Received By', 0, 0, 'C');
        $pdf->SetXY(130, $yPos + 2);
        $pdf->Cell($lineWidth, 6, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('stock_transfer_' . $transfer->id . '.pdf', 'I');
    }

}
