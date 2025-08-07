<?php

namespace App\Http\Controllers;

use App\Models\ProductionDetail;
use App\Models\ProductCategory;
use App\Models\ChartOfAccounts;
use App\Models\ProductionReceiving;
use App\Models\ProductionReceivingDetail;
use App\Models\Production;
use App\Models\Product;
use App\Models\MeasurementUnit;
use App\Models\PaymentVoucher;
 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->where('item_type', 'raw')->get();
        $units = MeasurementUnit::all();

        $allProducts = collect($products)->map(function ($product) {
            return (object)[
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->measurement_unit,
            ];
        });
        
        return view('production.create', compact('vendors', 'categories', 'allProducts', 'units'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:chart_of_accounts,id',
            'category_id' => 'required|exists:product_categories,id',
            'order_date' => 'required|date',
            'production_type' => 'required|string',
            'att.*' => 'nullable|file|max:2048',
            'item_details' => 'required|array|min:1',
            'item_details.*.product_id' => 'required|exists:products,id',
            'item_details.*.invoice_id' => 'required|exists:purchase_invoices,id',
            'item_details.*.qty' => 'required|numeric|min:0.01',
            'item_details.*unit' => 'required|exists:measurement_units,id',
            'item_details.*.item_rate' => 'required|numeric|min:0',
            'item_details.*.remarks' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            Log::info('Production Store: Start storing');

            // Save attachments
            $attachments = [];
            if ($request->hasFile('att')) {
                foreach ($request->file('att') as $file) {
                    $attachments[] = $file->store('attachments/productions', 'public');
                }
            }

            // Calculate total amount
            $totalAmount = collect($request->item_details)->sum(function ($item) {
                return $item['qty'] * $item['item_rate'];
            });

            // Create production
            $production = Production::create([
                'vendor_id' => $request->vendor_id,
                'category_id' => $request->category_id,
                'order_date' => $request->order_date,
                'production_type' => $request->production_type,
                'total_amount' => $totalAmount,
                'remarks' => $request->remarks,
                'attachments' => $attachments,
                'created_by' => auth()->id(),
            ]);

            // Save production item details
            if (is_array($request->item_details)) {
                foreach ($request->item_details as $item) {
                    $production->details()->create([
                        'production_id' => $production->id,
                        'invoice_id' => $item['invoice_id'],
                        'product_id' => $item['product_id'],
                        'qty' => $item['qty'],
                        'unit' => $item['item_unit'],
                        'rate' => $item['item_rate'],
                        'total_cost' => $item['item_rate'] * $item['qty'],
                        'remarks' => $item['remarks'] ?? null,
                    ]);
                }
            } else {
                throw new \Exception('Items data is not valid.');
            }

            // Auto-generate payment voucher if challan exists and production_type is sale_leather
            if (!empty($request->challan_no) && $request->production_type === 'sale_leather') {
                PaymentVoucher::create([
                    'date' => $request->order_date,
                    'ac_dr_sid' => $production->vendor_id, // Vendor becomes receivable (Dr)
                    'ac_cr_sid' => 5, // Raw Material Inventory (Cr)
                    'amount' => $totalAmount,
                    'remarks' => 'Amount ' . number_format($totalAmount, 2) . ' for leather in Production ID - ' . $production->id,
                    'attachments' => [], // Copy attachments if needed
                ]);

                Log::info('Payment Voucher auto-generated for production_id: ' . $production->id);
            }

            DB::commit();
            Log::info('Production Store: Success for production_id: ' . $production->id);

            return redirect()->route('production.index')->with('success', 'Production created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Production Store Error: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function edit($id)
    {
        $production = Production::with('details')->findOrFail($id);
        $vendors = ChartOfAccounts::where('account_type', 'vendor')->get();
        $categories = ProductCategory::all();
        $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->where('item_type', 'raw')->get();
        $units = MeasurementUnit::all();

        $allProducts = collect($products)->map(function ($product) {
            return (object)[
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->measurement_unit,
            ];
        });

        return view('production.edit', compact('production', 'vendors', 'categories', 'allProducts', 'units'));
    }


public function update(Request $request, $id)
{
    $request->validate([
        'vendor_id' => 'required|exists:chart_of_accounts,id',
        'category_id' => 'required|exists:product_categories,id',
        'order_date' => 'required|date',
        'production_type' => 'required|string',
        'att.*' => 'nullable|file|max:2048',
        'items' => 'required|array|min:1',
        'items.*.item_id' => 'required|exists:products,id',
        'items.*.invoice' => 'required|exists:purchase_invoices,id',
        'items.*.qty' => 'required|numeric|min:0.01',
        'items.*.item_unit' => 'required|string|max:50', // Use string if it's unit name, or change accordingly
        'items.*.rate' => 'required|numeric|min:0',
        'items.*.remarks' => 'nullable|string',
    ]);

    DB::beginTransaction();

    try {
        $production = Production::findOrFail($id);

        // Handle attachments
        $attachments = $production->attachments ?? [];
        if ($request->hasFile('att')) {
            foreach ($request->file('att') as $file) {
                $attachments[] = $file->store('attachments/productions', 'public');
            }
        }

        // Calculate total amount
        $totalAmount = collect($request->items)->sum(function ($item) {
            return $item['qty'] * $item['rate'];
        });

        // Update production
        $production->update([
            'vendor_id' => $request->vendor_id,
            'category_id' => $request->category_id,
            'order_date' => $request->order_date,
            'production_type' => $request->production_type,
            'total_amount' => $totalAmount,
            'remarks' => $request->remarks,
            'updated_by' => auth()->id(),
        ]);

        // Delete and reinsert details
        $production->details()->delete();

        foreach ($request->items as $item) {
            $production->details()->create([
                'production_id' => $production->id,
                'invoice_id' => $item['invoice'],
                'product_id' => $item['item_id'],
                'qty' => $item['qty'],
                'unit' => $item['item_unit'],
                'rate' => $item['rate'],
                'remarks' => $item['remarks'] ?? null,
            ]);
        }

        DB::commit();

        return redirect()->route('production.index')->with('success', 'Production updated successfully.');
    } catch (\Exception $e) {
        DB::rollBack();

        // Log full context for debugging
        Log::error('Production Update Error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all(),
            'production_id' => $id,
            'user_id' => auth()->id(),
        ]);

        return back()->withInput()->with('error', 'Failed to update production. Check logs.');
    }
}


    public function show($id)
    {
        $production = Production::with(['vendor', 'details.product'])->findOrFail($id);
        return view('production.show', compact('production'));
    }   
    
    public function summary($id)
    {
        $production = Production::with([
            'details.product',
            'receivings.details.product'
        ])->findOrFail($id);

        $rows = [];

        foreach ($production->details as $detail) {
            $rawProductName = $detail->product->name ?? '-';
            $issuedQty = $detail->qty;
            $unit = $detail->unit;

            // Sum all received pcs of finished product related to this raw product (if 1-to-1 mapping)
            $receivedPcs = $production->receivings->sum(function ($receiving) use ($detail) {
                return $receiving->details->where('product_id', $detail->product_id)->sum('received_qty');
            });

            $consumption = $receivedPcs > 0 ? $issuedQty / $receivedPcs : 0;

            $rows[] = [
                'name' => $rawProductName,
                'issued_qty' => $issuedQty,
                'unit' => $unit,
                'received_pcs' => $receivedPcs,
                'consumption' => $consumption,
            ];
        }

        // Generate PDF
        $pdf = new \TCPDF();
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Production Report #' . $production->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();

        $html = '
        <style>
            table { border-collapse: collapse; width: 100%; margin-top: 10px; }
            th, td { border: 1px solid #000; padding: 5px; font-size: 11px; text-align: center; }
            .header { font-size: 16px; font-weight: bold; text-align: center; margin-bottom: 10px; }
            .info-table td { border: none; font-size: 12px; }
        </style>

        <div class="header">Production Summary Report</div>

        <table class="info-table">
            <tr>
                <td><strong>Production ID:</strong> ' . $production->id . '</td>
                <td><strong>Date:</strong> ' . $production->order_date . '</td>
            </tr>
        </table>

        <br><strong>Item-wise Summary:</strong><br>

        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item</th>
                    <th>Qty Issued</th>
                    <th>Unit</th>
                    <th>Received Pcs</th>
                    <th>Consumption</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($rows as $index => $row) {
            $html .= '
            <tr>
                <td>' . ($index + 1) . '</td>
                <td>' . $row['name'] . '</td>
                <td>' . number_format($row['issued_qty'], 2) . '</td>
                <td>' . $row['unit'] . '</td>
                <td>' . $row['received_pcs'] . '</td>
                <td>' . number_format($row['consumption'], 4) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        if (!empty($production->remarks)) {
            $html .= '<br><br><strong>Remarks:</strong><br>' . nl2br($production->remarks);
        }

        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Output('production_summary_' . $production->id . '.pdf', 'I');
    }


}
