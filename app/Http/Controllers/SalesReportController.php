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

        // ✅ Default date range: 1st day of current month → today
        $from = $request->get('from_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
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
            // 🚧 Placeholder (Sales Return module coming soon)
            $returns = collect();
        }

        // --- CUSTOMER WISE (CW) ---
        if ($tab === 'CW') {
            $customerWise = SaleInvoice::with('account')
                ->whereBetween('date', [$from, $to])
                ->get()
                ->groupBy('account_id')
                ->map(function ($rows, $accountId) {
                    return (object)[
                        'customer' => $rows->first()->account->name ?? '',
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
