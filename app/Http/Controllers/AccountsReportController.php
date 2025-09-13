<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Voucher;
use App\Models\ChartOfAccounts;
use Carbon\Carbon;
use DB;

class AccountsReportController extends Controller
{
    public function accounts(Request $request)
    {
        $reportKey = $request->get('report', 'general_ledger');
        $from = $request->get('from_date', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->get('to_date', Carbon::now()->endOfMonth()->toDateString());

        $accounts = ChartOfAccounts::all();

        $reports = [];

        // ✅ General Ledger
        $generalLedger = collect();
        if ($request->filled('account_id')) {
            $generalLedger = Voucher::whereBetween('date', [$from, $to])
                ->where(function($q) use ($request) {
                    $q->where('ac_dr_sid', $request->account_id)
                      ->orWhere('ac_cr_sid', $request->account_id);
                })
                ->orderBy('date')
                ->get()
                ->map(function($v) use ($request) {
                    $debit  = $v->ac_dr_sid == $request->account_id ? $v->amount : 0;
                    $credit = $v->ac_cr_sid == $request->account_id ? $v->amount : 0;
                    return [
                        'date' => $v->date,
                        'voucher' => $v->voucher_type . ' #' . $v->id,
                        'account' => optional(ChartOfAccounts::find($request->account_id))->name,
                        'debit' => number_format($debit,2),
                        'credit' => number_format($credit,2),
                        'balance' => 0, // will update below
                    ];
                })
                ->values();

            // Running balance
            $balance = 0;
            foreach ($generalLedger as $row) {
                $balance += floatval($row['debit']) - floatval($row['credit']);
                $row['balance'] = number_format($balance, 2);
            }
        }
        $reports['general_ledger'] = $generalLedger;

        // ✅ Trial Balance
        $trialBalance = Voucher::select(
                DB::raw('coa.id as account_id'),
                DB::raw('coa.name as account'),
                DB::raw('coa.account_type as account_type'),
                DB::raw('SUM(CASE WHEN vouchers.ac_dr_sid = coa.id THEN vouchers.amount ELSE 0 END) as debit'),
                DB::raw('SUM(CASE WHEN vouchers.ac_cr_sid = coa.id THEN vouchers.amount ELSE 0 END) as credit')
            )
            ->join('chart_of_accounts as coa', function($join){
                $join->on('coa.id', '=', 'vouchers.ac_dr_sid')
                     ->orOn('coa.id', '=', 'vouchers.ac_cr_sid');
            })
            ->whereBetween('vouchers.date', [$from, $to])
            ->groupBy('coa.id','coa.name','coa.account_type')
            ->get()
            ->map(function($row){
                return [
                    'account' => $row->account,
                    'debit' => number_format($row->debit,2),
                    'credit' => number_format($row->credit,2),
                ];
            });
        $reports['trial_balance'] = $trialBalance;

        // ✅ Profit & Loss
        $revenue = $trialBalance->filter(fn($r)=> str_contains(strtolower($r['account']), 'sales'))->sum(fn($r)=>floatval(str_replace(',','',$r['credit'])));
        $expenses = $trialBalance->filter(fn($r)=> str_contains(strtolower($r['account']), 'expense'))->sum(fn($r)=>floatval(str_replace(',','',$r['debit'])));
        $reports['profit_loss'] = [
            ['Particulars' => 'Revenue', 'Amount' => number_format($revenue,2)],
            ['Particulars' => 'Expenses', 'Amount' => number_format($expenses,2)],
            ['Particulars' => 'Net Profit/Loss', 'Amount' => number_format($revenue - $expenses,2)],
        ];

        // ✅ Balance Sheet
        $assets = ChartOfAccounts::where('account_type','asset')->pluck('name')->toArray();
        $liabilities = ChartOfAccounts::where('account_type','liability')->pluck('name')->toArray();
        $max = max(count($assets), count($liabilities));
        $balanceSheet = [];
        for($i=0; $i<$max; $i++){
            $balanceSheet[] = [
                'asset' => $assets[$i] ?? '',
                'liability' => $liabilities[$i] ?? '',
            ];
        }
        $reports['balance_sheet'] = $balanceSheet;

        // ✅ Party Ledger (basic dump)
        $reports['party_ledger'] = Voucher::whereBetween('date', [$from,$to])
            ->get()
            ->map(fn($v)=>[
                'date'=>$v->date,
                'party'=>optional(ChartOfAccounts::find($v->ac_dr_sid))->name,
                'voucher'=>$v->voucher_type.' #'.$v->id,
                'debit'=>$v->ac_dr_sid ? $v->amount : 0,
                'credit'=>$v->ac_cr_sid ? $v->amount : 0,
                'balance'=>0,
            ]);

        // ✅ Receivables & Payables placeholders
        $reports['receivables'] = Voucher::whereBetween('date', [$from,$to])
            ->whereIn('voucher_type',['sale','sale_return'])
            ->get()
            ->map(fn($v)=>[
                'Customer'=>optional(ChartOfAccounts::find($v->ac_dr_sid))->name,
                'Total Receivable'=>$v->amount,
                '0-30 Days'=>0,
                '31-60 Days'=>0,
                '61-90 Days'=>0,
                '>90 Days'=>0,
            ]);

        $reports['payables'] = Voucher::whereBetween('date', [$from,$to])
            ->whereIn('voucher_type',['purchase','purchase_return'])
            ->get()
            ->map(fn($v)=>[
                'Vendor'=>optional(ChartOfAccounts::find($v->ac_cr_sid))->name,
                'Total Payable'=>$v->amount,
                '0-30 Days'=>0,
                '31-60 Days'=>0,
                '61-90 Days'=>0,
                '>90 Days'=>0,
            ]);

        // ✅ Cash Book
        $cashBook = Voucher::whereBetween('date', [$from,$to])
            ->where(function($q){ $q->where('ac_dr_sid',1)->orWhere('ac_cr_sid',1); })
            ->get()
            ->map(fn($v)=>[
                'date'=>$v->date,
                'particulars'=>$v->remarks,
                'debit'=>$v->ac_dr_sid==1 ? $v->amount:0,
                'credit'=>$v->ac_cr_sid==1 ? $v->amount:0,
                'balance'=>0,
            ]);
        $reports['cash_book'] = $cashBook;

        // ✅ Bank Book
        $bankBook = Voucher::whereBetween('date', [$from,$to])
            ->where(function($q){ $q->where('ac_dr_sid',2)->orWhere('ac_cr_sid',2); })
            ->get()
            ->map(fn($v)=>[
                'date'=>$v->date,
                'bank'=>'Main Bank',
                'debit'=>$v->ac_dr_sid==2 ? $v->amount:0,
                'credit'=>$v->ac_cr_sid==2 ? $v->amount:0,
                'balance'=>0,
            ]);
        $reports['bank_book'] = $bankBook;

        // ✅ Journal Book
        $reports['journal_book'] = Voucher::whereBetween('date', [$from,$to])
            ->orderBy('date')
            ->get()
            ->map(fn($v)=>[
                'date'=>$v->date,
                'voucher'=>$v->voucher_type.' #'.$v->id,
                'debit_account'=>optional(ChartOfAccounts::find($v->ac_dr_sid))->name,
                'credit_account'=>optional(ChartOfAccounts::find($v->ac_cr_sid))->name,
                'amount'=>$v->amount,
            ]);

        // ✅ Expense Analysis
        $reports['expense_analysis'] = $trialBalance->filter(fn($r)=> str_contains(strtolower($r['account']),'expense'));

        // ✅ Cash Flow
        $cashIn  = $cashBook->sum('debit');
        $cashOut = $cashBook->sum('credit');
        $reports['cash_flow'] = [
            ['Activity'=>'Operating','Inflows'=>$cashIn,'Outflows'=>$cashOut,'Net Flow'=>$cashIn-$cashOut],
        ];

        return view('reports.accounts_reports', compact('from','to','accounts','reports','reportKey'));
    }
}
