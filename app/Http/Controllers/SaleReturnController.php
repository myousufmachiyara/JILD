<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\SaleInvoice;
use App\Models\ChartOfAccounts;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleReturnController extends Controller
{
    public function index()
    {
        $returns = SaleReturn::with(['account','items.product','items.variation','saleInvoice'])
            ->latest()->get();

        return view('sale_returns.index', compact('returns'));
    }

    public function create()
    {
        return view('sale_returns.create', [
            'products'  => Product::where('item_type', 'fg')->get(),
            'customers'  => ChartOfAccounts::where('account_type', 'customer')->get(),
            'invoices'  => SaleInvoice::latest()->get(), // optional link to original
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'          => 'required|exists:customers,id',
            'return_date'          => 'required|date',
            'sale_invoice_no'      => 'nullable|string|max:50', // added
            'remarks'              => 'nullable|string|max:500',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.qty'          => 'required|numeric|min:1',
            'items.*.price'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Create Sale Return
            $return = SaleReturn::create([
                'customer_id'     => $validated['customer_id'],
                'return_date'     => $validated['return_date'],
                'sale_invoice_no' => $validated['sale_invoice_no'] ?? null, // new
                'remarks'         => $validated['remarks'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            // Create Sale Return Items
            foreach ($validated['items'] as $idx => $item) {
                try {
                    SaleReturnItem::create([
                        'sale_return_id' => $return->id,
                        'product_id'     => $item['product_id'],
                        'variation_id'   => $item['variation_id'] ?? null,
                        'qty'            => $item['qty'],
                        'price'          => $item['price'],
                    ]);
                } catch (\Throwable $itemEx) {
                    Log::error('[SaleReturn] Item save failed', [
                        'return_id'  => $return->id,
                        'item_index' => $idx,
                        'error'      => $itemEx->getMessage(),
                    ]);
                    throw $itemEx;
                }
            }

            DB::commit();
            Log::info('[SaleReturn] Created', ['return_id' => $return->id, 'by' => Auth::id()]);
            return redirect()->route('sale_return.index')->with('success', 'Sale return created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleReturn] Store failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withInput()->with('error', 'Error saving sale return. Please contact administrator.');
        }
    }

    public function edit($id)
    {
        $return = SaleReturn::with('items.product','items.variation','account','saleInvoice')->findOrFail($id);

        return view('sale_returns.edit', [
            'return'   => $return,
            'products' => Product::where('item_type', 'fg')->get(),
            'accounts' => ChartOfAccounts::where('account_type', 'customer')->get(),
            'invoices' => SaleInvoice::latest()->get(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'date'           => 'required|date',
            'account_id'     => 'required|exists:chart_of_accounts,id',
            'type'           => 'required|in:cash,credit',
            'sale_invoice_id'=> 'nullable|exists:sale_invoices,id',
            'discount'       => 'nullable|numeric|min:0',
            'remarks'        => 'nullable|string',
            'items'          => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.variation_id' => 'nullable|exists:product_variations,id',
            'items.*.sale_price'   => 'required|numeric|min:0',
            'items.*.disc_price'   => 'nullable|numeric|min:0',
            'items.*.quantity'     => 'required|numeric|min:0.01',
            'items.*.reason'       => 'nullable|string|max:500',
            'items.*.total'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $return = SaleReturn::findOrFail($id);

            $return->update([
                'date'           => $validated['date'],
                'account_id'     => $validated['account_id'],
                'sale_invoice_id'=> $validated['sale_invoice_id'] ?? null,
                'type'           => $validated['type'],
                'discount'       => $validated['discount'] ?? 0,
                'remarks'        => $request->remarks,
            ]);

            // Re-sync items
            $return->items()->delete();
            foreach ($validated['items'] as $item) {
                SaleReturnItem::create([
                    'sale_return_id' => $return->id,
                    'product_id'     => $item['product_id'],
                    'variation_id'   => $item['variation_id'] ?? null,
                    'sale_price'     => $item['sale_price'],
                    'discount'       => $item['disc_price'] ?? 0,
                    'quantity'       => $item['quantity'],
                    'reason'         => $item['reason'] ?? null,
                ]);
            }

            DB::commit();
            return redirect()->route('sale_returns.index')->with('success','Sale return updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[SaleReturn] Update failed', ['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
            return back()->withInput()->with('error','Error updating sale return. Please contact administrator.');
        }
    }

    public function show($id)
    {
        $return = SaleReturn::with('items.product','items.variation','account','saleInvoice')->findOrFail($id);
        return response()->json($return);
    }

    public function destroy($id)
    {
        try {
            $return = SaleReturn::findOrFail($id);
            $return->delete();
            return redirect()->route('sale_returns.index')->with('success','Sale return deleted.');
        } catch (\Throwable $e) {
            Log::error('[SaleReturn] Delete failed', ['error'=>$e->getMessage()]);
            return back()->with('error','Error deleting sale return.');
        }
    }

    public function print($id)
    {
        $return = SaleReturn::with(['account','items.product','items.variation','saleInvoice'])->findOrFail($id);

        $pdf = new \TCPDF();
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetCreator('Your App');
        $pdf->SetAuthor('Your Company');
        $pdf->SetTitle('Sale Return #'.$return->id);
        $pdf->SetMargins(10, 10, 10);
        $pdf->AddPage();
        $pdf->setCellPadding(1.5);

        $logoPath = public_path('assets/img/jild-Logo.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        $pdf->SetXY(130, 12);
        $info = '
        <table cellpadding="2" style="font-size:10px; line-height:14px;">
            <tr><td><b>Return #</b></td><td>'.$return->id.'</td></tr>
            <tr><td><b>Date</b></td><td>'.\Carbon\Carbon::parse($return->date)->format('d/m/Y').'</td></tr>
            <tr><td><b>Customer</b></td><td>'.($return->account->name ?? '-').'</td></tr>
            <tr><td><b>Type</b></td><td>'.ucfirst($return->type).'</td></tr>
            <tr><td><b>Against Invoice</b></td><td>'.($return->saleInvoice?->id ?? '-').'</td></tr>
        </table>';
        $pdf->writeHTML($info, false, false, false, false, '');

        $pdf->Line(60, 52.25, 200, 52.25);

        $pdf->SetXY(10, 48);
        $pdf->SetFillColor(23, 54, 93);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(50, 8, 'Sale Return', 0, 1, 'C', 1);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->Ln(5);
        $html = '<table border="0.3" cellpadding="4" style="text-align:center;font-size:10px;">
            <tr style="background-color:#f5f5f5; font-weight:bold;">
                <th width="7%">S.No</th>
                <th width="20%">Product</th>
                <th width="28%">Variation</th>
                <th width="8%">Qty</th>
                <th width="11%">Price</th>
                <th width="13%">Discount</th>
                <th width="13%">Total</th>
            </tr>';

        $i=0; $totalAmount=0;
        foreach ($return->items as $item) {
            $i++;
            $discPercent = $item->discount ?? 0;
            $discAmt = ($item->sale_price * $discPercent)/100;
            $netPrice = $item->sale_price - $discAmt;
            $lineTotal = $netPrice * $item->quantity;
            $totalAmount += $lineTotal;

            $html .= '
            <tr>
                <td>'.$i.'</td>
                <td>'.($item->product->name ?? '-').'</td>
                <td>'.($item->variation->sku ?? '-').'</td>
                <td>'.$item->quantity.'</td>
                <td align="right">'.number_format($item->sale_price, 2).'</td>
                <td align="right">'.number_format($item->discount, 0).'%</td>
                <td align="right">'.number_format($lineTotal, 2).'</td>
            </tr>';
        }

        $html .= '
            <tr>
                <td colspan="6" align="right"><b>Total</b></td>
                <td align="right"><b>'.number_format($totalAmount, 2).'</b></td>
            </tr>';

        if (!empty($return->discount)) {
            $totalAmount -= $return->discount;
            $html .= '
            <tr>
                <td colspan="6" align="right"><b>Return Discount (PKR)</b></td>
                <td align="right">'.number_format($return->discount, 2).'</td>
            </tr>';
        }

        $html .= '
            <tr style="background-color:#f5f5f5;">
                <td colspan="6" align="right"><b>Net Total</b></td>
                <td align="right"><b>'.number_format($totalAmount, 2).'</b></td>
            </tr>
        </table>';

        $pdf->writeHTML($html, true, false, true, false, '');

        if (!empty($return->remarks)) {
            $remarksHtml = '<b>Remarks:</b><br><span style="font-size:12px;">'.nl2br($return->remarks).'</span>';
            $pdf->writeHTML($remarksHtml, true, false, true, false, '');
        }

        $pdf->Ln(20);
        $y = $pdf->GetY();
        $w = 40;
        $pdf->Line(28, $y, 28+$w, $y);
        $pdf->Line(130, $y, 130+$w, $y);
        $pdf->SetXY(28, $y+2);  $pdf->Cell($w, 6, 'Received By', 0, 0, 'C');
        $pdf->SetXY(130, $y+2); $pdf->Cell($w, 6, 'Authorized By', 0, 0, 'C');

        return $pdf->Output('sale_return_'.$return->id.'.pdf', 'I');
    }
}
