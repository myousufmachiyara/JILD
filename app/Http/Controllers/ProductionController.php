<?php

namespace App\Http\Controllers;

use App\Models\ProductionDetail;
use App\Models\ProductCategory;
use App\Models\ChartOfAccounts;
use App\Models\ProductionReceiving;
use App\Models\Production;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Traits\PostsAccountingEntries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductionController extends Controller
{
    use PostsAccountingEntries;

    public function index()
    {
        $productions = Production::with(['vendor', 'category', 'details', 'receivings.details'])
            ->orderBy('id', 'desc')->get();
        return view('production.index', compact('productions'));
    }

    public function create()
    {
        $vendors    = ChartOfAccounts::where('account_type', 'vendor')->get();
        $categories = ProductCategory::all();
        $products   = Product::select('id', 'name', 'barcode', 'measurement_unit')
                             ->where('item_type', 'raw')->get();
        $units      = MeasurementUnit::all();

        $allProducts = $products->map(fn($p) => (object)[
            'id'   => $p->id,
            'name' => $p->name,
            'unit' => $p->measurement_unit,
        ]);

        return view('production.create', compact('vendors', 'categories', 'allProducts', 'units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id'                   => 'required|exists:chart_of_accounts,id',
            'category_id'                 => 'nullable|exists:product_categories,id',
            'order_date'                  => 'required|date',
            'production_type'             => 'required|in:cmt,sale_leather',
            'att.*'                       => 'nullable|file|max:2048',
            'item_details'                => 'required|array|min:1',
            'item_details.*.product_id'   => 'required|exists:products,id',
            'item_details.*.variation_id' => 'nullable|exists:product_variations,id',
            'item_details.*.invoice_id'   => 'nullable|exists:purchase_invoices,id',
            'item_details.*.qty'          => 'required|numeric|min:0.01',
            'item_details.*.item_unit'    => 'required|exists:measurement_units,id',
            'item_details.*.item_rate'    => 'required|numeric|min:0',
            'item_details.*.desc'         => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $attachments = [];
            if ($request->hasFile('att')) {
                foreach ($request->file('att') as $file) {
                    $attachments[] = $file->store('attachments/productions', 'public');
                }
            }

            $totalAmount = collect($request->item_details)->sum(fn($i) => $i['qty'] * $i['item_rate']);

            $production = Production::create([
                'vendor_id'       => $request->vendor_id,
                'category_id'     => $request->category_id ?? null,
                'order_date'      => $request->order_date,
                'production_type' => $request->production_type,
                'remarks'         => $request->remarks,
                'attachments'     => $attachments,
                'created_by'      => auth()->id(),
            ]);

            foreach ($request->item_details as $item) {
                $production->details()->create([
                    'product_id'   => $item['product_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'invoice_id'   => $item['invoice_id']   ?? null,
                    'qty'          => $item['qty'],
                    'unit'         => $item['item_unit'],
                    'rate'         => $item['item_rate'],
                    'desc'         => $item['desc'] ?? null,
                ]);
            }

            $production->loadMissing('details');
            $this->postProductionEntries($production);

            DB::commit();
            return redirect()->route('production.index')->with('success', 'Production order created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Production Store Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Something went wrong: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $production = Production::with(['details.variation', 'details.product'])->findOrFail($id);
        $vendors    = ChartOfAccounts::where('account_type', 'vendor')->get();
        $categories = ProductCategory::all();
        $products   = Product::with('variations')
                             ->select('id', 'name', 'barcode', 'measurement_unit')
                             ->where('item_type', 'raw')->get();
        $units      = MeasurementUnit::all();

        $allProducts = $products->map(fn($p) => (object)[
            'id'         => $p->id,
            'name'       => $p->name,
            'unit'       => $p->measurement_unit,
            'variations' => $p->variations->map(fn($v) => (object)['id' => $v->id, 'sku' => $v->sku]),
        ]);

        return view('production.edit', compact('production', 'vendors', 'categories', 'allProducts', 'units'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'vendor_id'                   => 'required|exists:chart_of_accounts,id',
            'category_id'                 => 'nullable|exists:product_categories,id',
            'order_date'                  => 'required|date',
            'production_type'             => 'required|in:cmt,sale_leather',
            'attachments.*'               => 'nullable|file|max:2048',
            'item_details'                => 'required|array|min:1',
            'item_details.*.item_id'      => 'required|exists:products,id',
            'item_details.*.variation_id' => 'nullable|exists:product_variations,id',
            'item_details.*.invoice'      => 'nullable|exists:purchase_invoices,id',
            'item_details.*.qty'          => 'required|numeric|min:0.01',
            'item_details.*.item_unit'    => 'required|exists:measurement_units,id',
            'item_details.*.rate'         => 'required|numeric|min:0',
            'item_details.*.desc'         => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $production  = Production::findOrFail($id);
            $attachments = $production->attachments ?? [];

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $attachments[] = $file->store('attachments/productions', 'public');
                }
            }

            $totalAmount = collect($request->item_details)->sum(fn($i) => $i['qty'] * $i['rate']);

            $production->update([
                'vendor_id'       => $request->vendor_id,
                'category_id'     => $request->category_id ?? null,
                'order_date'      => $request->order_date,
                'production_type' => $request->production_type,
                'remarks'         => $request->remarks,
                'attachments'     => $attachments,
            ]);

            $production->details()->delete();
            foreach ($request->item_details as $item) {
                $production->details()->create([
                    'product_id'   => $item['item_id'],
                    'variation_id' => $item['variation_id'] ?? null,
                    'invoice_id'   => $item['invoice']      ?? null,
                    'qty'          => $item['qty'],
                    'unit'         => $item['item_unit'],
                    'rate'         => $item['rate'],
                    'desc'         => $item['desc'] ?? null,
                ]);
            }

            $production->loadMissing('details');
            $this->postProductionEntries($production);

            DB::commit();
            return redirect()->route('production.index')->with('success', 'Production order updated successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Production Update Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return back()->withInput()->with('error', 'Failed to update: ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $production = Production::findOrFail($id);
            $this->deleteVoucherEntries($production);
            $production->details()->delete();
            $production->delete();
            DB::commit();
            return redirect()->route('production.index')->with('success', 'Production order deleted.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    public function consumptionReport()
    {
        $productions = Production::with([
            'vendor',
            'details.product',
            'receivings.details.product',
            'wastageReceivings.details.product',
        ])->orderBy('id', 'desc')->get();

        $productAvgConsumption = [];
        foreach ($productions as $prod) {
            $totalFG = $prod->receivings->flatMap->details->sum('received_qty');
            if ($totalFG <= 0) continue;
            foreach ($prod->details as $detail) {
                $pid = $detail->product_id;
                if (!isset($productAvgConsumption[$pid])) {
                    $productAvgConsumption[$pid] = [
                        'total_raw' => 0,
                        'total_fg'  => 0,
                        'name'      => $detail->product->name ?? '-',
                    ];
                }
                $productAvgConsumption[$pid]['total_raw'] += $detail->qty;
                $productAvgConsumption[$pid]['total_fg']  += $totalFG;
            }
        }
        foreach ($productAvgConsumption as &$avg) {
            $avg['avg_consumption'] = $avg['total_fg'] > 0
                ? round($avg['total_raw'] / $avg['total_fg'], 4)
                : 0;
        }
        unset($avg);

        $productionStats = $productions->map(function ($prod) use ($productAvgConsumption) {
            $totalRaw = $prod->details->sum('qty');
            $totalFG  = $prod->receivings->flatMap->details->sum('received_qty');
            $wastage  = $prod->wastageReceivings->flatMap->details->sum('quantity');
            $atMfr    = max(0, $totalRaw - $wastage);
            $ratio    = $totalRaw > 0 && $totalFG > 0 ? round($totalRaw / $totalFG, 4) : null;

            $alert = false;
            foreach ($prod->details as $detail) {
                $pid    = $detail->product_id;
                $avgCon = $productAvgConsumption[$pid]['avg_consumption'] ?? null;
                if ($ratio !== null && $avgCon && $ratio > ($avgCon * 1.1)) {
                    $alert = true;
                    break;
                }
            }

            return [
                'production'  => $prod,
                'total_raw'   => $totalRaw,
                'total_fg'    => $totalFG,
                'wastage'     => $wastage,
                'raw_at_mfr'  => $atMfr,
                'consumption' => $ratio,
                'alert'       => $alert,
            ];
        });

        return view('production.consumption-report', compact('productionStats', 'productAvgConsumption'));
    }

    public function getProductProductions(Request $request, $productId)
    {
        try {
            $variationId = $request->get('variation_id');
            $query       = ProductionDetail::with('production')->where('product_id', $productId);

            if ($variationId) {
                $query->where('variation_id', $variationId);
            } else {
                $query->whereNull('variation_id');
            }

            return response()->json($query->get()->map(fn($d) => ['id' => $d->production_id, 'rate' => $d->rate]));

        } catch (\Throwable $e) {
            Log::error("Error fetching productions: " . $e->getMessage());
            return response()->json(['error' => 'Something went wrong.'], 500);
        }
    }

    public function show($id)
    {
        $production = Production::with(['vendor', 'details.product', 'receivings.details.product'])->findOrFail($id);
        return view('production.show', compact('production'));
    }

    public function summary($id)
    {
        $production = Production::with([
            'details.product.measurementUnit',
            'receivings.details.product.measurementUnit',
            'wastageReceivings.details.product',
        ])->findOrFail($id);
        // ... existing PDF code unchanged ...
    }

    public function print($id)
    {
        $production = Production::with([
            'vendor',
            'details.product.measurementUnit',
            'details.variation',
            'details.invoice',
        ])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Jild');
        $pdf->SetAuthor('Jild');
        $pdf->SetTitle('Production Order #' . $production->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        // Logo
        $logoPath = public_path('assets/img/Jild-Logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        // Info box
        $pdf->SetXY(130, 12);
        $pdf->writeHTML('
            <table border="1" cellpadding="4" style="font-size:10px;line-height:14px;border-collapse:collapse;">
                <tr><td><b>Production #</b></td><td>' . $production->id . '</td></tr>
                <tr><td><b>Date</b></td><td>' . Carbon::parse($production->order_date)->format('d/m/Y') . '</td></tr>
                <tr><td><b>Type</b></td><td>' . ucwords(str_replace('_', ' ', $production->production_type)) . '</td></tr>
                <tr><td><b>Vendor</b></td><td>' . ($production->vendor->name ?? '-') . '</td></tr>
            </table>',
        false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        // Title bar
        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Production Order', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        // Items table
        $html = '
        <table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5;font-weight:bold;">
                <th width="6%">S.No</th>
                <th width="22%">Item</th>
                <th width="20%">Variation</th>
                <th width="14%">Invoice #</th>
                <th width="14%">Qty</th>
                <th width="12%">Rate</th>
                <th width="12%">Total</th>
            </tr>';

        $count       = 0;
        $totalAmount = 0;

        foreach ($production->details as $detail) {
            $count++;
            $amount       = $detail->qty * $detail->rate;
            $totalAmount += $amount;

            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td>' . ($detail->product->name ?? '-') . '</td>
                <td>' . ($detail->variation->sku ?? '-') . '</td>
                <td>' . ($detail->invoice_id ? '#' . $detail->invoice_id : '-') . '</td>
                <td>' . number_format($detail->qty, 2) . ' ' . ($detail->product->measurementUnit->shortcode ?? '') . '</td>
                <td align="right">' . number_format($detail->rate, 2) . '</td>
                <td align="right">' . number_format($amount, 2) . '</td>
            </tr>';
        }

        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="6" align="right"><b>Total Amount</b></td>
                <td align="right"><b>' . number_format($totalAmount, 2) . '</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        if (!empty($production->remarks)) {
            $pdf->writeHTML(
                '<b>Remarks:</b><br><span style="font-size:12px;">' . nl2br($production->remarks) . '</span>',
                true, false, true, false, ''
            );
        }

        // Signatures
        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->Line(28, $y, 68, $y);
        $pdf->Line(130, $y, 170, $y);
        $pdf->SetXY(28,  $y + 2); $pdf->Cell(40, 6, 'Issued By',     0, 0, 'C');
        $pdf->SetXY(130, $y + 2); $pdf->Cell(40, 6, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('production_' . $production->id . '.pdf', 'I');
    }

    public function printGatepass($id)
    {
        $production = Production::with([
            'vendor',
            'details.product.measurementUnit',
            'details.variation',
        ])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Jild');
        $pdf->SetAuthor('Jild');
        $pdf->SetTitle('Gate Pass #' . $production->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        $logoPath = public_path('assets/img/Jild-Logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        $pdf->SetXY(130, 12);
        $pdf->writeHTML('
            <table border="1" cellpadding="4" style="font-size:10px;line-height:14px;border-collapse:collapse;">
                <tr><td><b>Gate Pass #</b></td><td>' . $production->id . '</td></tr>
                <tr><td><b>Date</b></td><td>' . Carbon::parse($production->order_date)->format('d/m/Y') . '</td></tr>
                <tr><td><b>Vendor</b></td><td>' . ($production->vendor->name ?? '-') . '</td></tr>
            </table>',
        false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Production Gate Pass', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(5);

        $html = '
        <table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5;font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="28%">Item</th>
                <th width="25%">Variation</th>
                <th width="20%">Qty</th>
                <th width="20%">Remarks</th>
            </tr>';

        $count = 0;
        foreach ($production->details as $detail) {
            $count++;
            $html .= '
            <tr>
                <td>' . $count . '</td>
                <td>' . ($detail->product->name ?? '-') . '</td>
                <td>' . ($detail->variation->sku ?? '-') . '</td>
                <td>' . number_format($detail->qty, 2) . ' ' . ($detail->product->measurementUnit->shortcode ?? '') . '</td>
                <td>' . ($detail->desc ?? '-') . '</td>
            </tr>';
        }

        $html .= '</table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->Line(28,  $y, 68,  $y);
        $pdf->Line(130, $y, 170, $y);
        $pdf->SetXY(28,  $y + 2); $pdf->Cell(40, 6, 'Issued By',   0, 0, 'C');
        $pdf->SetXY(130, $y + 2); $pdf->Cell(40, 6, 'Received By', 0, 0, 'C');

        return $pdf->Output('gatepass_' . $production->id . '.pdf', 'I');
    }

    // ── Accounting ────────────────────────────────────────────────────

    /**
     * CMT:          DR WIP (104003)    CR Raw Material Stock (104002)
     * Sale Leather: DR Vendor          CR Raw Material Stock (104002)
     */
    private function postProductionEntries(Production $production): void
    {
        $totalAmount = $production->details->sum(fn($d) => $d->qty * $d->rate);

        if ($totalAmount <= 0) return;

        if ($production->production_type === 'sale_leather') {
            $this->syncVoucherEntries(
                $production,
                'production',
                $production->order_date,
                [
                    [
                        'dr_id'   => $production->vendor_id,
                        'cr'      => '104002',
                        'amount'  => $totalAmount,
                        'remarks' => 'Leather sold to manufacturer',
                    ],
                ]
            );
        } else {
            $this->syncVoucherEntries(
                $production,
                'production',
                $production->order_date,
                [
                    [
                        'dr'      => '104003',
                        'cr'      => '104002',
                        'amount'  => $totalAmount,
                        'remarks' => 'Raw material sent for CMT production',
                    ],
                ]
            );
        }

        Log::info('[Production] Accounting synced', [
            'production_id' => $production->id,
            'type'          => $production->production_type,
            'total'         => $totalAmount,
        ]);
    }
}