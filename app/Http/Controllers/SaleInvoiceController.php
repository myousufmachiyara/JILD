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
        $request->validate([
            'invoice_no' => 'required|unique:sale_invoices',
            'date' => 'required|date',
            'account_id' => 'required|exists:chart_of_accounts,id',
            'type' => 'required|in:cash,credit',
            'convance_charges' => 'nullable|numeric',
            'other_expenses' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.variation_id' => 'required|exists:product_variations,id',
            'items.*.production_id' => 'required|exists:productions,id',
            'items.*.cost_price' => 'required|numeric',
            'items.*.sale_price' => 'required|numeric',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            // Create Sale Invoice
            $invoice = SaleInvoice::create([
                'invoice_no' => $request->invoice_no,
                'date' => $request->date,
                'account_id' => $request->account_id,
                'type' => $request->type,
                'convance_charges' => $request->convance_charges ?? 0,
                'other_expenses' => $request->other_expenses ?? 0,
                'created_by' => Auth::id(),
            ]);

            // Create related items
            foreach ($request->items as $item) {
                SaleInvoiceItem::create([
                    'sale_invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'variation_id' => $item['variation_id'],
                    'production_id' => $item['production_id'],
                    'cost_price' => $item['cost_price'],
                    'sale_price' => $item['sale_price'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Sale Invoice created successfully.']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
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
