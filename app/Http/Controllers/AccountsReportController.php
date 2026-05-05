<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Voucher;
use App\Models\ChartOfAccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountsReportController extends Controller
{
    public function accounts(Request $request)
    {
        $from            = $request->from_date ?? Carbon::now()->startOfMonth()->toDateString();
        $to              = $request->to_date   ?? Carbon::now()->endOfMonth()->toDateString();
        $report          = $request->report    ?? 'general_ledger';
        $chartOfAccounts = ChartOfAccounts::orderBy('name')->get();
        $accountId       = $request->account_id ? (int)$request->account_id : null;

        $reports = [
            'general_ledger'   => $this->generalLedger($accountId, $from, $to),
            'trial_balance'    => $this->trialBalance($from, $to),
            'profit_loss'      => $this->profitLoss($from, $to),
            'balance_sheet'    => $this->balanceSheet($from, $to),
            'party_ledger'     => $this->partyLedger($from, $to, $accountId),
            'receivables'      => $this->receivables($from, $to),
            'payables'         => $this->payables($from, $to),
            'cash_book'        => $this->cashBook($from, $to),
            'bank_book'        => $this->bankBook($from, $to),
            'journal_book'     => $this->journalBook($from, $to),
            'expense_analysis' => $this->expenseAnalysis($from, $to),
            'cash_flow'        => $this->cashFlow($from, $to),
        ];

        return view('reports.accounts_reports', compact(
            'reports', 'from', 'to', 'report', 'chartOfAccounts'
        ));
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function fmt($value): string
    {
        return number_format((float)$value, 2);
    }

    private function unformat($value): float
    {
        return (float)str_replace(',', '', $value);
    }

    private function runningBalance(array $rows): array
    {
        $balance = 0;
        foreach ($rows as &$row) {
            $balance += $this->unformat($row['debit']) - $this->unformat($row['credit']);
            $row['balance'] = $this->fmt($balance);
        }
        unset($row);
        return $rows;
    }

    // ── General Ledger ────────────────────────────────────────────────

    private function generalLedger(?int $accountId, string $from, string $to): array
    {
        if (!$accountId) {
            return [];
        }

        $vouchers = Voucher::with(['debitAccount', 'creditAccount'])
            ->whereBetween('date', [$from, $to])
            ->where(fn($q) => $q->where('ac_dr_sid', $accountId)
                                ->orWhere('ac_cr_sid', $accountId))
            ->orderBy('date')
            ->get();

        $rows = $vouchers->map(function ($v) use ($accountId) {
            $isDebit = $v->ac_dr_sid == $accountId;

            $contra = $isDebit
                ? ($v->creditAccount->name ?? '-')
                : ($v->debitAccount->name  ?? '-');

            // ← Fix: concatenate instead of using ?? inside string interpolation
            $refId   = $v->source_id ?? $v->id;
            $type    = ucwords(str_replace('_', ' ', $v->voucher_type));
            $remarks = $v->remarks ? ' — ' . \Str::limit($v->remarks, 40) : '';

            return [
                'date'    => $v->date,
                'voucher' => $type . ' #' . $refId . $remarks,
                'account' => $contra,
                'debit'   => $isDebit  ? $this->fmt($v->amount) : '0.00',
                'credit'  => !$isDebit ? $this->fmt($v->amount) : '0.00',
                'balance' => '0.00',
            ];
        })->toArray();

        return $this->runningBalance($rows);
    }

    // ── Trial Balance ─────────────────────────────────────────────────

    private function trialBalance(string $from, string $to): \Illuminate\Support\Collection
    {
        $debits = DB::table('vouchers')
            ->join('chart_of_accounts as coa', 'vouchers.ac_dr_sid', '=', 'coa.id')
            ->whereBetween('vouchers.date', [$from, $to])
            ->whereNull('vouchers.deleted_at')
            ->select(
                'coa.id',
                'coa.name',
                'coa.account_type',
                DB::raw('SUM(vouchers.amount) as total_debit'),
                DB::raw('0 as total_credit')
            )
            ->groupBy('coa.id', 'coa.name', 'coa.account_type');

        $credits = DB::table('vouchers')
            ->join('chart_of_accounts as coa', 'vouchers.ac_cr_sid', '=', 'coa.id')
            ->whereBetween('vouchers.date', [$from, $to])
            ->whereNull('vouchers.deleted_at')
            ->select(
                'coa.id',
                'coa.name',
                'coa.account_type',
                DB::raw('0 as total_debit'),
                DB::raw('SUM(vouchers.amount) as total_credit')
            )
            ->groupBy('coa.id', 'coa.name', 'coa.account_type');

        return $debits->unionAll($credits)
            ->get()
            ->groupBy('id')
            ->map(function ($rows) {
                $first  = $rows->first();
                $debit  = $rows->sum('total_debit');
                $credit = $rows->sum('total_credit');
                return [
                    'account'      => $first->name,
                    'account_type' => $first->account_type,
                    'debit'        => $this->fmt($debit),
                    'credit'       => $this->fmt($credit),
                    'net'          => $this->fmt($debit - $credit),
                ];
            })
            ->values();
    }

    // ── Profit & Loss ─────────────────────────────────────────────────

    private function profitLoss(string $from, string $to): array
    {
        $trial = $this->trialBalance($from, $to);

        $revenue = $trial->whereIn('account_type', ['revenue'])
            ->sum(fn($r) => $this->unformat($r['credit']) - $this->unformat($r['debit']));

        $cogs = $trial->whereIn('account_type', ['cogs'])
            ->sum(fn($r) => $this->unformat($r['debit']) - $this->unformat($r['credit']));

        $expenses = $trial->whereIn('account_type', ['expense'])
            ->sum(fn($r) => $this->unformat($r['debit']) - $this->unformat($r['credit']));

        $grossProfit = $revenue - $cogs;
        $netProfit   = $grossProfit - $expenses;

        return [
            ['particulars' => 'Revenue',             'amount' => $this->fmt($revenue)],
            ['particulars' => 'Cost of Goods Sold',  'amount' => $this->fmt($cogs)],
            ['particulars' => 'Gross Profit',         'amount' => $this->fmt($grossProfit)],
            ['particulars' => 'Operating Expenses',   'amount' => $this->fmt($expenses)],
            ['particulars' => 'Net Profit',           'amount' => $this->fmt($netProfit)],
        ];
    }

    // ── Balance Sheet ─────────────────────────────────────────────────

    private function balanceSheet(string $from, string $to): array
    {
        $trial = $this->trialBalance($from, $to);

        $assets = $trial->whereIn('account_type', ['asset', 'cash', 'bank', 'customer'])
            ->map(fn($r) => [
                'name'   => $r['account'],
                'amount' => $this->fmt($this->unformat($r['debit']) - $this->unformat($r['credit'])),
            ])->values();

        $liabilities = $trial->whereIn('account_type', ['liability', 'vendor'])
            ->map(fn($r) => [
                'name'   => $r['account'],
                'amount' => $this->fmt($this->unformat($r['credit']) - $this->unformat($r['debit'])),
            ])->values();

        $equity = $trial->whereIn('account_type', ['equity'])
            ->map(fn($r) => [
                'name'   => $r['account'],
                'amount' => $this->fmt($this->unformat($r['credit']) - $this->unformat($r['debit'])),
            ])->values();

        $liabsAndEquity = $liabilities->concat($equity)->values();
        $max  = max($assets->count(), $liabsAndEquity->count(), 1);
        $rows = [];

        for ($i = 0; $i < $max; $i++) {
            $rows[] = [
                'asset'     => $assets[$i]['name']          ?? '',
                'asset_amt' => $assets[$i]['amount']        ?? '',
                'liab'      => $liabsAndEquity[$i]['name']   ?? '',
                'liab_amt'  => $liabsAndEquity[$i]['amount'] ?? '',
            ];
        }

        return $rows;
    }

    // ── Party Ledger ──────────────────────────────────────────────────

    private function partyLedger(string $from, string $to, ?int $accountId = null): \Illuminate\Support\Collection
    {
        $query = Voucher::with(['debitAccount', 'creditAccount'])
            ->whereBetween('date', [$from, $to])
            ->orderBy('date');

        if ($accountId) {
            $query->where(fn($q) => $q->where('ac_dr_sid', $accountId)
                                      ->orWhere('ac_cr_sid', $accountId));
        } else {
            $partyAccountIds = ChartOfAccounts::whereIn('account_type', ['customer', 'vendor'])
                ->pluck('id');
            $query->where(fn($q) => $q->whereIn('ac_dr_sid', $partyAccountIds)
                                      ->orWhereIn('ac_cr_sid', $partyAccountIds));
        }

        $rows = $query->get()->map(function ($v) use ($accountId) {
            $resolvedId = $accountId ?? (
                in_array($v->debitAccount->account_type ?? '', ['customer', 'vendor'])
                    ? $v->ac_dr_sid
                    : $v->ac_cr_sid
            );

            $isDebit = $v->ac_dr_sid == $resolvedId;
            $party   = $isDebit
                ? ($v->debitAccount->name  ?? 'N/A')
                : ($v->creditAccount->name ?? 'N/A');

            $type    = ucwords(str_replace('_', ' ', $v->voucher_type));
            $remarks = $v->remarks ? ' — ' . \Str::limit($v->remarks, 40) : '';

            return [
                'date'    => $v->date,
                'party'   => $party,
                'voucher' => $type . ' #' . $v->id . $remarks,
                'debit'   => $isDebit  ? $this->fmt($v->amount) : '0.00',
                'credit'  => !$isDebit ? $this->fmt($v->amount) : '0.00',
                'balance' => '0.00',
            ];
        })->toArray();

        return collect($this->runningBalance($rows));
    }

    // ── Receivables ───────────────────────────────────────────────────

    private function receivables(string $from, string $to): \Illuminate\Support\Collection
    {
        $customerIds = ChartOfAccounts::where('account_type', 'customer')->pluck('id');

        return $customerIds->map(function ($id) use ($from, $to) {
            $account     = ChartOfAccounts::find($id);
            $totalDebit  = Voucher::where('ac_dr_sid', $id)->whereBetween('date', [$from, $to])->sum('amount');
            $totalCredit = Voucher::where('ac_cr_sid', $id)->whereBetween('date', [$from, $to])->sum('amount');
            $balance     = $totalDebit - $totalCredit;

            if ($balance <= 0) {
                return null;
            }

            return [
                'customer'         => $account->name,
                'total_receivable' => $this->fmt($balance),
                '0_30'             => $this->fmt($this->agingBucket($id, $to, 0,  30,  'debit')),
                '31_60'            => $this->fmt($this->agingBucket($id, $to, 31, 60,  'debit')),
                '61_90'            => $this->fmt($this->agingBucket($id, $to, 61, 90,  'debit')),
                'over_90'          => $this->fmt($this->agingBucket($id, $to, 91, null,'debit')),
            ];
        })->filter()->values();
    }

    // ── Payables ──────────────────────────────────────────────────────

    private function payables(string $from, string $to): \Illuminate\Support\Collection
    {
        $vendorIds = ChartOfAccounts::where('account_type', 'vendor')->pluck('id');

        return $vendorIds->map(function ($id) use ($from, $to) {
            $account     = ChartOfAccounts::find($id);
            $totalDebit  = Voucher::where('ac_dr_sid', $id)->whereBetween('date', [$from, $to])->sum('amount');
            $totalCredit = Voucher::where('ac_cr_sid', $id)->whereBetween('date', [$from, $to])->sum('amount');
            $balance     = $totalCredit - $totalDebit;

            if ($balance <= 0) {
                return null;
            }

            return [
                'vendor'        => $account->name,
                'total_payable' => $this->fmt($balance),
                '0_30'          => $this->fmt($this->agingBucket($id, $to, 0,  30,  'credit')),
                '31_60'         => $this->fmt($this->agingBucket($id, $to, 31, 60,  'credit')),
                '61_90'         => $this->fmt($this->agingBucket($id, $to, 61, 90,  'credit')),
                'over_90'       => $this->fmt($this->agingBucket($id, $to, 91, null,'credit')),
            ];
        })->filter()->values();
    }

    private function agingBucket(int $accountId, string $toDate, int $daysFrom, ?int $daysTo, string $side): float
    {
        $end   = Carbon::parse($toDate)->subDays($daysFrom);
        $start = $daysTo ? Carbon::parse($toDate)->subDays($daysTo) : null;

        $q = Voucher::where($side === 'debit' ? 'ac_dr_sid' : 'ac_cr_sid', $accountId)
                    ->where('date', '<=', $end);

        if ($start) {
            $q->where('date', '>=', $start);
        }

        return (float)$q->sum('amount');
    }

    // ── Cash Book ─────────────────────────────────────────────────────

    private function cashBook(string $from, string $to): array
    {
        $cashIds = ChartOfAccounts::where('account_type', 'cash')->pluck('id');

        $rows = Voucher::whereBetween('date', [$from, $to])
            ->where(fn($q) => $q->whereIn('ac_dr_sid', $cashIds)
                                ->orWhereIn('ac_cr_sid', $cashIds))
            ->with(['debitAccount', 'creditAccount'])
            ->orderBy('date')
            ->get()
            ->map(function ($v) use ($cashIds) {
                $isDebit = $cashIds->contains($v->ac_dr_sid);
                $contra  = $isDebit
                    ? ($v->creditAccount->name ?? '-')
                    : ($v->debitAccount->name  ?? '-');

                $type = ucwords(str_replace('_', ' ', $v->voucher_type));

                return [
                    'date'        => $v->date,
                    'particulars' => $type . ' #' . $v->id . ' | ' . $contra,
                    'debit'       => $isDebit  ? $this->fmt($v->amount) : '0.00',
                    'credit'      => !$isDebit ? $this->fmt($v->amount) : '0.00',
                    'balance'     => '0.00',
                ];
            })->toArray();

        return $this->runningBalance($rows);
    }

    // ── Bank Book ─────────────────────────────────────────────────────

    private function bankBook(string $from, string $to): array
    {
        $bankIds = ChartOfAccounts::where('account_type', 'bank')->pluck('id');

        $rows = Voucher::whereBetween('date', [$from, $to])
            ->where(fn($q) => $q->whereIn('ac_dr_sid', $bankIds)
                                ->orWhereIn('ac_cr_sid', $bankIds))
            ->with(['debitAccount', 'creditAccount'])
            ->orderBy('date')
            ->get()
            ->map(function ($v) use ($bankIds) {
                $isDebit = $bankIds->contains($v->ac_dr_sid);
                $contra  = $isDebit
                    ? ($v->creditAccount->name ?? '-')
                    : ($v->debitAccount->name  ?? '-');

                $type = ucwords(str_replace('_', ' ', $v->voucher_type));

                return [
                    'date'    => $v->date,
                    'bank'    => $type . ' #' . $v->id . ' | ' . $contra,
                    'debit'   => $isDebit  ? $this->fmt($v->amount) : '0.00',
                    'credit'  => !$isDebit ? $this->fmt($v->amount) : '0.00',
                    'balance' => '0.00',
                ];
            })->toArray();

        return $this->runningBalance($rows);
    }

    // ── Journal / Day Book ────────────────────────────────────────────

    private function journalBook(string $from, string $to): \Illuminate\Support\Collection
    {
        return Voucher::with(['debitAccount', 'creditAccount'])
            ->whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get()
            ->map(function ($v) {
                $type = ucwords(str_replace('_', ' ', $v->voucher_type));
                return [
                    'date'       => $v->date,
                    'voucher'    => $type . ' #' . $v->id,
                    'dr_account' => $v->debitAccount->name  ?? '-',
                    'cr_account' => $v->creditAccount->name ?? '-',
                    'amount'     => $this->fmt($v->amount),
                ];
            });
    }

    // ── Expense Analysis ──────────────────────────────────────────────

    private function expenseAnalysis(string $from, string $to): \Illuminate\Support\Collection
    {
        return $this->trialBalance($from, $to)
            ->whereIn('account_type', ['expense', 'cogs'])
            ->map(fn($r) => [
                'expense_head' => $r['account'],
                'amount'       => $this->fmt(
                    $this->unformat($r['debit']) - $this->unformat($r['credit'])
                ),
            ])
            ->filter(fn($r) => $this->unformat($r['amount']) > 0)
            ->values();
    }

    // ── Cash Flow ─────────────────────────────────────────────────────

    private function cashFlow(string $from, string $to): array
    {
        $cashBankIds = ChartOfAccounts::whereIn('account_type', ['cash', 'bank'])->pluck('id');

        $inflows  = Voucher::whereBetween('date', [$from, $to])
                           ->whereIn('ac_dr_sid', $cashBankIds)->sum('amount');
        $outflows = Voucher::whereBetween('date', [$from, $to])
                           ->whereIn('ac_cr_sid', $cashBankIds)->sum('amount');

        return [
            [
                'activity' => 'Cash & Bank Inflows',
                'inflows'  => $this->fmt($inflows),
                'outflows' => '0.00',
                'net flow' => $this->fmt($inflows),
            ],
            [
                'activity' => 'Cash & Bank Outflows',
                'inflows'  => '0.00',
                'outflows' => $this->fmt($outflows),
                'net flow' => $this->fmt(-$outflows),
            ],
            [
                'activity' => 'Net Cash Flow',
                'inflows'  => $this->fmt($inflows),
                'outflows' => $this->fmt($outflows),
                'net flow' => $this->fmt($inflows - $outflows),
            ],
        ];
    }
}