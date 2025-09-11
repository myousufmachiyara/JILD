<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AccountTransaction; // example model (replace with your table/model)
use App\Models\ChartOfAccounts;
use Carbon\Carbon;

class AccountsReportController extends Controller
{
    public function accountsReports(Request $request)
    {
        $tab = $request->get('tab', 'GL'); // default: General Ledger

        // Default date range (last 30 days)
        $from = $request->get('from_date', Carbon::now()->subDays(30)->format('Y-m-d'));
        $to   = $request->get('to_date', Carbon::now()->format('Y-m-d'));

        $accounts = ChartOfAccounts::all();
        $generalLedger = collect();
        $trialBalance  = collect();
        $plAccounts    = collect();
        $balanceSheet  = collect();

        // --- GENERAL LEDGER ---
        if ($tab === 'GL' && $request->filled('account_id')) {
            $generalLedger = AccountTransaction::where('account_id', $request->account_id)
                ->whereBetween('date', [$from, $to])
                ->orderBy('date')
                ->get()
                ->map(function ($tx) {
                    return (object)[
                        'date'        => $tx->date,
                        'description' => $tx->description,
                        'debit'       => $tx->debit,
                        'credit'      => $tx->credit,
                        'balance'     => $tx->balance ?? ($tx->debit - $tx->credit),
                    ];
                });
        }

        // --- TRIAL BALANCE ---
        if ($tab === 'TB') {
            $trialBalance = ChartOfAccounts::with('transactions')
                ->get()
                ->map(function ($acc) {
                    return (object)[
                        'account' => $acc->name,
                        'debit'   => $acc->transactions->sum('debit'),
                        'credit'  => $acc->transactions->sum('credit'),
                        'balance' => $acc->transactions->sum('debit') - $acc->transactions->sum('credit'),
                    ];
                });
        }

        // --- PROFIT & LOSS ---
        if ($tab === 'PL') {
            $plAccounts = [
                'revenue' => AccountTransaction::whereHas('account', fn($q) => $q->where('type', 'Revenue'))
                    ->whereBetween('date', [$from, $to])->sum('credit'),
                'expenses' => AccountTransaction::whereHas('account', fn($q) => $q->where('type', 'Expenses'))
                    ->whereBetween('date', [$from, $to])->sum('debit'),
            ];
            $plAccounts['net'] = $plAccounts['revenue'] - $plAccounts['expenses'];
        }

        // --- BALANCE SHEET ---
        if ($tab === 'BS') {
            $balanceSheet = [
                'assets'     => ChartOfAccount::where('type', 'Assets')->with('transactions')->get(),
                'liabilities'=> ChartOfAccount::where('type', 'Liabilities')->with('transactions')->get(),
                'equity'     => ChartOfAccount::where('type', 'Equity')->with('transactions')->get(),
            ];
        }

        return view('reports.accounts_reports', compact(
            'tab',
            'from',
            'to',
            'accounts',
            'generalLedger',
            'trialBalance',
            'plAccounts',
            'balanceSheet'
        ));
    }
}
