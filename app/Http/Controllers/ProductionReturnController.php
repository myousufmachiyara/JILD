<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

use App\Models\ProductionReturn;
use App\Models\ProductionReturnItem;
use App\Models\Production;
use App\Models\Product;
use App\Models\ChartOfAccounts;
use App\Models\MeasurementUnit;

class ProductionReturnController extends Controller
{
    public function index()
    {
        $returns = ProductionReturn::with('vendor')
            ->withSum('items as total_amount', \DB::raw('quantity * price'))
            ->latest()
            ->get();

        return view('production-return.index', compact('returns'));
    }

    public function create()
    {
        $productions = Production::with('vendor')->get();
        $products = Product::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();

        return view('production-return.create', compact('productions', 'products', 'vendors', 'units'));
    }

    public function store(Request $request)
    {
        Log::info('Storing Production Return', ['request' => $request->all()]);

        $request->validate([
            'vendor_id'   => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'remarks'     => 'nullable|string|max:1000',

            // Validate each item row
            'items.*.item_id'       => 'required|exists:products,id',
            'items.*.variation_id'  => 'nullable|exists:product_variations,id',
            'items.*.production_id' => 'required|exists:productions,id',
            'items.*.quantity'      => 'required|numeric|min:0',
            'items.*.unit'          => 'required|exists:measurement_units,id',
            'items.*.price'         => 'required|numeric|min:0',
            'items.*.amount'        => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $return = ProductionReturn::create([
                'vendor_id'   => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks'     => $request->remarks,
                'created_by'  => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                ProductionReturnItem::create([
                    'production_return_id' => $return->id,
                    'item_id'              => $item['item_id'],
                    'variation_id'         => $item['variation_id'] ?? null,
                    'production_id'        => $item['production_id'],
                    'quantity'             => $item['quantity'],
                    'unit_id'              => $item['unit'],
                    'price'                => $item['price'],
                    'amount'               => $item['amount'],
                ]);
            }

            DB::commit();
            return redirect()->route('production_return.index')->with('success', 'Production Return saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Production Return store failed', ['error' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'Failed to save: '.$e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $return = ProductionReturn::with([
            'items',
            'items.item',
            'items.variation',
            'items.production',
            'items.unit'
        ])->findOrFail($id);

        $products = Product::all();
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $units = MeasurementUnit::all();
        $productions = Production::with('vendor')->get();

        return view('production-return.edit', compact('return', 'products', 'vendors', 'units', 'productions'));
    }

    public function update(Request $request, $id)
    {
        Log::info('ProductionReturn Update Request', $request->all());

        $request->validate([
            'vendor_id'   => 'required|exists:chart_of_accounts,id',
            'return_date' => 'required|date',
            'remarks'     => 'nullable|string|max:1000',
            'total_amount'      => 'required|numeric|min:0',
            'net_amount_hidden' => 'required|numeric|min:0',

            'items.*.item_id'       => 'required|exists:products,id',
            'items.*.variation_id'  => 'nullable|exists:product_variations,id',
            'items.*.production_id' => 'required|exists:productions,id',
            'items.*.quantity'      => 'required|numeric|min:0',
            'items.*.unit'          => 'required|exists:measurement_units,id',
            'items.*.price'         => 'required|numeric|min:0',
            'items.*.amount'        => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $return = ProductionReturn::findOrFail($id);

            $return->update([
                'vendor_id'   => $request->vendor_id,
                'return_date' => $request->return_date,
                'remarks'     => $request->remarks,
                'total_amount' => $request->total_amount,
                'net_amount'   => $request->net_amount_hidden,
            ]);

            // Remove old items
            ProductionReturnItem::where('production_return_id', $return->id)->delete();

            // Insert updated items
            foreach ($request->items as $item) {
                ProductionReturnItem::create([
                    'production_return_id' => $return->id,
                    'item_id'              => $item['item_id'],
                    'variation_id'         => $item['variation_id'] ?? null,
                    'production_id'        => $item['production_id'],
                    'quantity'             => $item['quantity'],
                    'unit_id'              => $item['unit'],
                    'price'                => $item['price'],
                    'amount'               => $item['amount'],
                    'remarks'              => $item['remarks'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('production_return.index')->with('success', 'Production Return updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ProductionReturn update failed', ['error' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'Failed to update: '.$e->getMessage()]);
        }
    }

    public function print($id)
    {
        $return = ProductionReturn::with(['vendor', 'items.item', 'items.unit', 'items.production'])
            ->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // --- Logo ---
        $logoPath = public_path('assets/img/Jild-Logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        // --- Return Info ---
        $pdf->SetXY(130, 12);
        $returnInfo = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Return #</b></td><td>' . $return->id . '</td></tr>
            <tr><td><b>Date</b></td><td>' . \Carbon\Carbon::parse($return->return_date)->format('d/m/Y') . '</td></tr>
            <tr><td><b>Vendor</b></td><td>' . ($return->vendor->name ?? '-') . '</td></tr>
        </table>';
        $pdf->writeHTML($returnInfo, false, false, false, false, '');

        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(60, 8, 'Production Return', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        // --- Items Table ---
        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="28%">Item Name</th>
                <th width="12%">Prod. #</th>
                <th width="20%">Qty</th>
                <th width="15%">Rate</th>
                <th width="18%">Amount</th>
            </tr>';

        $totalAmount = 0;
        $count = 0;

        foreach ($return->items as $item) {
            $count++;
            $amount = $item->price * $item->quantity;
            $totalAmount += $amount;

            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td>' . ($item->item->name ?? '-') . '</td>
                <td>' . ($item->production->id ?? '-') . '</td>
                <td>' . number_format($item->quantity, 2) . ' ' . ($item->unit->shortcode ?? '-') . '</td>
                <td align="right">' . number_format($item->price, 2) . '</td>
                <td align="right">' . number_format($amount, 2) . '</td>
            </tr>';
        }

        $html .= '
            <tr>
                <td colspan="5" align="right"><b>Total</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr></table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // --- Remarks ---
        if (!empty($return->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($return->remarks) . '</span>';
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

        return $pdf->Output('production_return_' . $return->id . '.pdf', 'I');
    }
}
