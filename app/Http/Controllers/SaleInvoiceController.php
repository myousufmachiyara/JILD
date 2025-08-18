<?php

namespace App\Http\Controllers;

use App\Models\SaleInvoice;
use App\Models\SaleInvoiceItem;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use App\Models\Production;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleInvoiceController extends Controller
{

    public function create()
    {
        return view('sales.create', [
            'products' => Product::where('item_type', 'fg')->get(),
            'productions' => Production::latest()->get(),
            'accounts' => ChartOfAccounts::where('account_type', 'customer')->get(), // or your logic
        ]);
    }

    public function store(Request $request)
    {
        // ✅ Validate incoming request
        $validated = $request->validate([
            'date'         => 'required|date',
            'account_id'   => 'required|exists:chart_of_accounts,id',
            'type'         => 'required|in:cash,credit',
            'discount'     => 'nullable|numeric|min:0',
            'net_amount'   => 'required|numeric|min:0',
            'items'        => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.sale_price'   => 'required|numeric|min:0',
            'items.*.disc_price'   => 'nullable|numeric|min:0',
            'items.*.quantity'     => 'required|numeric|min:1',
            'items.*.total'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // ✅ Create invoice
            $invoice = SaleInvoice::create([
                'invoice_no'       => $validated['invoice_no'] ?? 'INV-' . time(),
                'date'             => $validated['date'],
                'account_id'       => $validated['account_id'],
                'type'             => $validated['type'],
                'discount'         => $validated['discount'] ?? 0,
                'net_amount'       => $validated['net_amount'],
                'other_expenses'   => 0, // adjust if needed
                'convance_charges' => 0, // adjust if needed
                'created_by'       => Auth::id(),
                'remarks'          => $request->remarks,
            ]);

            // ✅ Save items
            foreach ($validated['items'] as $index => $item) {
                try {
                    SaleInvoiceItem::create([
                        'sale_invoice_id' => $invoice->id,
                        'product_id'      => $item['product_id'],
                        'variation_id'    => $item['variation_id'] ?? null,
                        'cost_price'      => 0, // you can fetch from product/production later
                        'sale_price'      => $item['sale_price'],
                        'discount'        => $item['disc_price'] ?? 0,
                        'quantity'        => $item['quantity'],
                        'total'           => $item['total'],
                    ]);
                } catch (\Throwable $itemEx) {
                    // Log individual item failure but don’t stop loop unless you want strict rollback
                    Log::error('[SaleInvoice] Item save failed', [
                        'invoice_id'   => $invoice->id,
                        'item_index'   => $index,
                        'item_data'    => $item,
                        'error'        => $itemEx->getMessage(),
                    ]);
                    throw $itemEx; // rethrow to rollback whole transaction
                }
            }

            DB::commit();
            Log::info('[SaleInvoice] Created successfully', [
                'invoice_id' => $invoice->id,
                'created_by' => Auth::id(),
            ]);

            return redirect()->route('sale_invoices.index')
                ->with('success', 'Sale invoice created successfully.');

        } catch (\Throwable $e) {
            DB::rollBack();

            // Log full error
            Log::error('[SaleInvoice] Store failed', [
                'request_data' => $request->all(),
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
            ]);

            return back()->withInput()
                ->with('error', 'Error saving invoice. Please contact administrator.');
        }
    }

    public function index()
    {
        $invoices = SaleInvoice::with('items.product', 'items.variation', 'items.production', 'account')
            ->latest()->get();

        return view('sales.index', compact('invoices'));
    }

    public function show($id)
    {
        $invoice = SaleInvoice::with('items.product', 'items.variation', 'items.production', 'account')
            ->findOrFail($id);
        return response()->json($invoice);
    }
}
