<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Production;
use App\Models\ProductionReceiving;
use Carbon\Carbon;

class ProductionReportController extends Controller
{
    public function productionReports(Request $request)
    {
        $tab = $request->get('tab', 'RMI'); // default tab

        // Default date range (last 30 days)
        $from = $request->get('from_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to   = $request->get('to_date', Carbon::now()->format('Y-m-d'));

        // ensure all variables the Blade might expect are defined
        $rawIssued   = collect();
        $produced    = collect();
        $costings    = collect();   // <-- added to avoid "Undefined variable $costings"
        $wip         = collect();
        $yieldWaste  = collect();

        // --- RAW MATERIAL ISSUED (RMI) ---
        if ($tab === 'RMI') {
            $rawIssued = Production::with('details.product')
                ->whereBetween('order_date', [$from, $to])
                ->get()
                ->flatMap(function ($prod) {
                    return $prod->details->map(function ($detail) use ($prod) {
                        return (object)[
                            'date'       => $prod->order_date,
                            'production' => $prod->id,
                            'item_name'  => $detail->product->name ?? '',
                            'qty'        => $detail->quantity,
                            'rate'       => $detail->rate,
                            'total'      => $detail->quantity * $detail->rate,
                        ];
                    });
                });
        }

        // --- PRODUCTION RECEIVED (PR) ---
        if ($tab === 'PR') {
            $produced = ProductionReceiving::with('production', 'details.product')
                ->whereBetween('rec_date', [$from, $to])
                ->get()
                ->flatMap(function ($rec) {
                    return $rec->details->map(function ($detail) use ($rec) {
                        return (object)[
                            'date'       => $rec->rec_date,
                            'production' => $rec->production->id ?? '',
                            'item_name'  => $detail->product->name ?? '',
                            'qty'        => $detail->received_qty,
                            'm_cost'     => $detail->manufacturing_cost,
                            'total'      => $detail->received_qty * $detail->manufacturing_cost,
                        ];
                    });
                });
        }

        // --- COSTING (CR) ---
        // Note: ProjectCosting model/table doesn't exist yet, so we leave $costings empty.
        // When you add ProjectCosting, replace the following block with real query:
        //
        // use App\Models\ProjectCosting;
        // $costings = ProjectCosting::with('project')->whereBetween('date', [$from,$to])->get()->map(...);
        //

        // --- WIP (WORK IN PROGRESS) ---
        if ($tab === 'WIP') {
            $wip = Production::with('details')
                ->where('status', 'in-progress')
                ->whereBetween('order_date', [$from, $to])
                ->get()
                ->map(function ($prod) {
                    return (object)[
                        'date'        => $prod->order_date,
                        'production'  => $prod->id,
                        'total_items' => $prod->details->count(),
                        'status'      => ucfirst($prod->status),
                    ];
                });
        }

        // --- YIELD / WASTE (YW) ---
        if ($tab === 'YW') {
            $yieldWaste = Production::whereBetween('order_date', [$from, $to])
                ->get()
                ->map(function ($prod) {
                    return (object)[
                        'date'       => $prod->order_date,
                        'production' => $prod->id,
                        'yield'      => $prod->yield ?? 0,
                        'waste'      => $prod->waste ?? 0,
                    ];
                });
        }

        return view('reports.production_reports', compact(
            'tab',
            'from',
            'to',
            'rawIssued',
            'produced',
            'costings',   // <-- now passed to the view
            'wip',
            'yieldWaste'
        ));
    }
}
