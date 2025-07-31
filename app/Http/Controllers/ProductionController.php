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
        $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->get();
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
            'order_date' => 'required|date',
            'production_type' => 'required|string',
            'challan_no' => 'nullable|string',
            'att.*' => 'nullable|file|max:2048',
            'item_details' => 'required|array|min:1',
            'item_details.*.product_id' => 'required|exists:products,id',
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
                'order_date' => $request->order_date,
                'production_type' => $request->production_type,
                'challan_no' => $request->challan_no,
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
                        'product_id' => $item['product_id'],
                        'qty' => $item['qty'],
                        'unit' => $item['item_unit'],
                        'rate' => $item['item_rate'],
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
    $products = Product::select('id', 'name', 'barcode', 'measurement_unit')->get();
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
        'order_date' => 'required|date',
        'production_type' => 'required|string',
        'challan_no' => 'nullable|string',
        'attachments.*' => 'nullable|file|max:2048',
        'item_details' => 'required|array|min:1',
        'item_details.*.product_id' => 'required|exists:products,id',
        'item_details.*.qty' => 'required|numeric|min:0.01',
        'item_details.*.item_unit' => 'required|exists:measurement_units,id',
        'item_details.*.item_rate' => 'required|numeric|min:0',
    ]);

    DB::beginTransaction();

    try {
        $production = Production::findOrFail($id);

        // Handle attachments
        $attachments = $production->attachments ?? [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachments[] = $file->store('attachments/productions', 'public');
            }
        }

        // Calculate total amount
        $totalAmount = collect($request->item_details)->sum(fn($item) => $item['qty'] * $item['item_rate']);

        $production->update([
            'vendor_id' => $request->vendor_id,
            'order_date' => $request->order_date,
            'production_type' => $request->production_type,
            'challan_no' => $request->challan_no,
            'total_amount' => $totalAmount,
            'remarks' => $request->remarks,
            'attachments' => $attachments,
            'updated_by' => auth()->id(),
        ]);

        // Delete old details and re-insert
        $production->details()->delete();

        foreach ($request->item_details as $item) {
            $production->details()->create([
                'product_id' => $item['product_id'],
                'qty' => $item['qty'],
                'unit' => $item['item_unit'],
                'rate' => $item['item_rate'],
                'remarks' => $item['remarks'] ?? null,
            ]);
        }

        DB::commit();
        return redirect()->route('production.index')->with('success', 'Production updated successfully.');
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Production Update Error: " . $e->getMessage());
        return back()->withInput()->with('error', 'Failed to update production.');
    }
}


    public function show($id)
    {
        $production = Production::with(['vendor', 'details.product'])->findOrFail($id);
        return view('production.show', compact('production'));
    }    
}
