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
        $production = Production::with(['details.product.measurementUnit', 'receivings.details.product.measurementUnit'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor('Your App Name');
        $pdf->SetTitle('Production Costing');
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Ln(5);

        // Header
        $html = '
        <table class="info-table">
            <tr>
                <td><strong>Production ID:</strong> ' . $production->id . '</td>
                <td style="text-align:right"><strong>Date:</strong> ' . $production->order_date . '</td>
            </tr>
        </table>';
        $pdf->writeHTML($html, true, false, true, false, '');

        // Raw Details Table
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Raw Details', 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(40, 7, 'Item', 1);
        $pdf->Cell(25, 7, 'Raw', 1);
        $pdf->Cell(25, 7, 'Rate', 1);
        $pdf->Cell(30, 7, 'Total Cost', 1);
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 9);

        $totalRawGiven = 0;
        foreach ($production->details as $raw) {
            $itemName = optional($raw->product)->name ?? 'N/A';
            $rawQty = $raw->qty;
            $rawUnit = optional($raw->product->measurementUnit)->shortcode ?? '';
            $rate = $raw->rate;
            $totalCost = $rawQty * $rate;

            $totalRawGiven += $rawQty;

            $pdf->Cell(40, 7, $itemName, 1);
            $pdf->Cell(25, 7, number_format($rawQty, 2) . ' ' . $rawUnit, 1);
            $pdf->Cell(25, 7, number_format($rate, 2), 1);
            $pdf->Cell(30, 7, number_format($totalCost, 2), 1);
            $pdf->Ln();
        }

        // Finish Goods Table
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Finish Good Details', 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(60, 7, 'Product', 1);
        $pdf->Cell(30, 7, 'Qty', 1);
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', 9);

        // Aggregate products by product_id
        $productSummary = [];
        foreach ($production->receivings as $receiving) {
            foreach ($receiving->details as $detail) {
                $productId = $detail->product_id;
                $productName = optional($detail->product)->name ?? '-';
                $unit = optional($detail->product->measurementUnit)->shortcode ?? '-';
                $receivedQty = $detail->received_qty;

                if (!isset($productSummary[$productId])) {
                    $productSummary[$productId] = [
                        'name' => $productName,
                        'unit' => $unit,
                        'qty' => 0
                    ];
                }

                $productSummary[$productId]['qty'] += $receivedQty;
            }
        }

        // Display aggregated data
        $totalProductsReceived = 0;
        foreach ($productSummary as $product) {
            $totalProductsReceived += $product['qty'];
            $pdf->Cell(60, 7, $product['name'], 1);
            $pdf->Cell(30, 7, number_format($product['qty'], 2) . ' ' . $product['unit'], 1);
            $pdf->Ln();
        }

        // Summary Table
        $pdf->Ln(5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'Summary', 0, 1);
        $pdf->Ln(2);

        $consumption = $totalProductsReceived > 0
            ? ($totalRawGiven / $totalProductsReceived)
            : 0;

        $pdf->SetFont('helvetica', '', 9);
        $pdf->Cell(60, 7, 'Total Raw Given', 1);
        $pdf->Cell(60, 7, number_format($totalRawGiven, 2), 1);
        $pdf->Ln();
        $pdf->Cell(60, 7, 'Total Products Received', 1);
        $pdf->Cell(60, 7, number_format($totalProductsReceived, 2), 1);
        $pdf->Ln();
        $pdf->Cell(60, 7, 'Consumption (%)', 1);
        $pdf->Cell(60, 7, number_format($consumption, 2), 1);
        $pdf->Ln();

        $pdf->Output('production_' . $production->id . '.pdf', 'I');
    }
}
