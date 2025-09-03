<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Carbon\Carbon;

class InventoryReportController extends Controller
{
    public function index()
    {
        $items = Product::all();
        $from = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $to   = $request->to_date   ?? Carbon::now()->toDateString();

        return view('reports.inventory_reports', compact('items','from','to'));
    }
}
