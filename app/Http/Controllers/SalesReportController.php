<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SaleInvoice;
use Carbon\Carbon;

class SalesReportController extends Controller
{
    public function saleReports(Request $request)
    {
        $tab = $request->get('tab', 'SR'); // default Sales Register

        // Default date range (last 30 days)
        $from = $request->get('from_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to   = $request->get('to_date', Carbon::now()->format('Y-m-d'));

        $sales        = collect();
        $returns      = collect();
        $customerWise = collect();

        // --- SALES REGISTER (SR) ---
        if ($tab === 'SR') {
            $sales = SaleInvoice::with('account')
                ->whereBetween('date', [$from, $to])
                ->get()
                ->map(function ($sale) {
                    return (object)[
                        'date'      => $sale->date,
                        'invoice'   => $sale->id,
                        'customer'  => $sale->account->name ?? '',
                        'total'     => $sale->total_amount ?? 0,
                    ];
                });
        }

        // --- SALES RETURN (SRET) ---
        if ($tab === 'SRET') {
            // Assuming you have SaleReturn model/table
            // If not, leave it empty for now
            // Example:
            // $returns = SaleReturn::with('customer')->whereBetween('date', [$from, $to])->get()->map(...);
            $returns = collect(); 
        }

        // --- CUSTOMER WISE (CW) ---
        if ($tab === 'CW') {
            $customerWise = SaleInvoice::with('customer')
                ->whereBetween('date', [$from, $to])
                ->get()
                ->groupBy('customer_id')
                ->map(function ($rows, $custId) {
                    return (object)[
                        'customer' => $rows->first()->customer->name ?? '',
                        'total'    => $rows->sum('total_amount'),
                        'count'    => $rows->count(),
                    ];
                })
                ->values();
        }

        return view('reports.sales_reports', compact(
            'tab',
            'from',
            'to',
            'sales',
            'returns',
            'customerWise'
        ));
    }
}
